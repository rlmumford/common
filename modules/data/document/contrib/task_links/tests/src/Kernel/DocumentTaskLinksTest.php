<?php

namespace Drupal\Tests\document_task_links\Kernel;

use Drupal\checklist\Entity\ChecklistItem;
use Drupal\document\Entity\Document;
use Drupal\document\Entity\DocumentType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\task\Entity\Task;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Views;

/**
 * Tests recorded relationships through real storage and access-aware Views.
 *
 * @group document
 */
class DocumentTaskLinksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'text', 'filter', 'options', 'datetime',
    'entity', 'views', 'document', 'document_task_links', 'document_task_links_test',
    'task', 'task_ui', 'task_context', 'task_checklist', 'checklist',
    'plugin_reference', 'typed_data', 'typed_data_plus', 'typed_data_reference',
    'typed_data_context_assignment',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'file', 'document', 'document_review', 'task', 'checklist_item'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('task_checklist', ['task_checklist_request']);
    $this->installSchema('document_task_links', ['document_task_link']);
    $this->installSchema('checklist', ['checklist_attempt', 'checklist_attempt_head', 'checklist_attempt_event']);
    $this->installConfig(['system', 'user', 'task_ui', 'document_task_links']);
    User::create(['uid' => 1, 'name' => 'root'])->save();
    Role::create(['id' => 'worker', 'label' => 'Worker', 'permissions' => ['view assigned tasks', 'view any document']])->save();
    $user = User::create(['name' => 'Worker', 'roles' => ['worker']]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);
    DocumentType::create(['id' => 'filing', 'label' => 'Filing'])->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Creates a saved document.
   */
  protected function document(string $label = 'Filing'): Document {
    $document = Document::create(['type' => 'filing', 'label' => $label]);
    $document->save();
    return $document;
  }

  /**
   * Direct, nested and multiple outcome references yield one task row.
   */
  public function testRelationshipsAndHistory(): void {
    $document = $this->document();
    $other = $this->document('Other');
    $task = Task::create([
      'title' => 'Review filing',
      'assignee' => $this->container->get('current_user')->id(),
      'input_document' => $document->id(),
      'context' => [
        ['key' => 'documents[0]', 'value' => $document->id()],
        ['key' => 'packet', 'blob' => ['documents' => [$document->id(), $other->id()], 'number' => $other->id()]],
      ],
    ]);
    $task->save();
    $item = ChecklistItem::create([
      'checklist_type' => 'document_work_test',
      'name' => 'produce',
      'title' => 'Produce document',
      'handler' => ['id' => 'simply_checkable'],
      'checklist' => ['target_id' => $task->id(), 'checklist_key' => 'checklist'],
      'outcomes' => [['key' => 'document', 'value' => $document->id()]],
    ]);
    $item->save();
    $index = $this->container->get('document_task_links.index');
    $links = $index->observations((int) $task->id());
    $this->assertCount(5, $links);
    $this->assertCount(1, array_filter($links, fn($link) => $link['relation'] === 'output'));
    $view = $this->view($document);
    $this->assertCount(1, $view->result);
    $this->assertEquals(1, $view->total_rows);
    $task->resolve()->save();
    $this->assertCount(1, $this->view($document)->result);
    // Updating bytes does not rewrite the revision observed on existing links.
    $revision = $document->getRevisionId();
    $document->setNewRevision(TRUE);
    $document->save();
    $task->save();
    foreach ($index->observations((int) $task->id(), (int) $document->id()) as $link) {
      $this->assertEquals($revision, $link['observed_revision']);
    }
    $task->set('input_document', NULL)->set('context', [])->save();
    $item->set('outcomes', [['key' => 'document', 'value' => $other->id()]])->save();
    $old = $index->observations((int) $task->id(), (int) $document->id());
    $this->assertCount(4, $old);
    $this->assertCount(4, array_filter($old, fn($link) => $link['removed'] !== NULL));
    $this->assertCount(1, $this->view($document)->result);
    $task->set('input_document', $document->id())->save();
    $this->assertCount(5, $index->observations((int) $task->id(), (int) $document->id()));
    $before = $index->observations((int) $task->id());
    foreach (['task', 'checklist_item'] as $type) {
      $this->assertNotNull($index->backfill($type, 0));
    }
    $this->assertSame($before, $index->observations((int) $task->id()));
    $item->delete();
    $this->assertNotNull($index->observations((int) $task->id(), (int) $other->id())[0]['removed']);
    $document->delete();
    $this->assertSame([], $index->observations((int) $task->id(), (int) $document->id()));
    $task->delete();
    $this->assertSame([], $index->observations((int) $task->id()));
  }

  /**
   * Saving an unpublished task revision does not change the current links.
   */
  public function testNonDefaultTaskRevision(): void {
    $document = $this->document();
    $other = $this->document('Draft input');
    $task = Task::create(['title' => 'Review', 'input_document' => $document->id()]);
    $task->save();
    $index = $this->container->get('document_task_links.index');
    $before = $index->observations((int) $task->id());
    $task->setNewRevision(TRUE);
    $task->isDefaultRevision(FALSE);
    $task->set('input_document', $other->id())->save();
    $this->assertSame($before, $index->observations((int) $task->id()));
    $index->backfill('task', 0);
    $this->assertSame($before, $index->observations((int) $task->id()));
  }

  /**
   * Both access filters and the pager operate on distinct tasks.
   */
  public function testPaginationAndAccess(): void {
    $document = $this->document();
    for ($i = 0; $i < 27; $i++) {
      Task::create([
        'title' => 'Review ' . $i,
        'assignee' => $i === 26 ? 1 : $this->container->get('current_user')->id(),
        'input_document' => $document->id(),
        'context' => [['key' => 'document', 'value' => $document->id()]],
      ])->save();
    }
    $view = $this->view($document);
    $this->assertEquals(26, $view->total_rows);
    $this->assertCount(25, $view->result);
    $view = $this->view($document, 1);
    $this->assertCount(1, $view->result);
    $role = Role::load('worker');
    $role->revokePermission('view any document')->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('document')->resetCache();
    $this->assertCount(0, $this->view($document)->result);
  }

  /**
   * Backfill is bounded and current links are restored without guessed history.
   */
  public function testBackfillAndRollback(): void {
    $document = $this->document();
    $task = Task::create(['title' => 'Older task', 'input_document' => $document->id()]);
    $task->save();
    $database = $this->container->get('database');
    $database->delete('document_task_link')->execute();
    $index = $this->container->get('document_task_links.index');
    $this->assertSame((int) $task->id(), $index->backfill('task', 0, 1));
    $this->assertNull($index->backfill('task', (int) $task->id(), 1));
    $this->assertCount(1, $index->observations((int) $task->id()));
    $transaction = $database->startTransaction();
    $task->set('input_document', NULL)->save();
    $transaction->rollBack();
    unset($transaction);
    $this->assertNull($index->observations((int) $task->id())[0]['removed']);
  }

  /**
   * Executes the actual document View, including query access and pager.
   */
  protected function view(Document $document, int $page = 0) {
    $view = Views::getView('document_related_tasks');
    $view->setDisplay('embed');
    $view->setArguments([(string) $document->id()]);
    $view->setCurrentPage($page);
    $view->execute();
    return $view;
  }

}
