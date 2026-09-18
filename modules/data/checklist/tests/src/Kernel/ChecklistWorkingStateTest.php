<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests working state persistence, cleanup, privacy and upgrades.
 *
 * @group checklist
 */
class ChecklistWorkingStateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'checklist_state_test',
    'plugin_reference', 'typed_data', 'typed_data_plus', 'typed_data_reference',
    'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // Individual tests install current or pre-state item storage.
    $this->installConfig(['system', 'user']);
    FieldStorageConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'type' => 'checklist',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create(['field_name' => 'work', 'entity_type' => 'user', 'bundle' => 'user'])->save();
  }

  /**
   * Creates a host with an opt-in stateful item and an ordinary decision.
   */
  protected function host(bool $save = TRUE): User {
    $host = User::create([
      'name' => 'Host',
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'worker' => ['title' => 'Worker', 'handler' => 'state_test', 'handler_configuration' => []],
            'decision' => [
              'title' => 'Decision',
              'handler' => 'decision',
              'handler_configuration' => ['options' => ['yes' => ['label' => 'Yes']]],
            ],
          ],
        ],
      ],
    ]);
    if ($save) {
      $host->save();
    }
    $this->container->get('current_user')->setAccount($host);
    return $host;
  }

  /**
   * Failure retains typed state across reloads without publishing outcomes.
   */
  public function testFailureRetention(): void {
    $this->installEntitySchema('checklist_item');
    $host = $this->host();
    $checklist = $host->work->checklist;
    $item = $checklist->getItem('worker');
    $item->setWorkingState('run_id', 'pending-123')
      ->setWorkingState('completed', 2)
      ->setWorkingState('steps', ['first', 'second'])
      ->setWorkingState('details', ['label' => 'Intermediate'])
      ->setWorkingState('owner', $host)
      ->setOutcome('result', 'Published')
      ->setFailed()
      ->save();
    $item = $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($item->id());
    $this->assertTrue($item->isFailed());
    $state = $item->get('state');
    $this->assertSame('pending-123', $state->get('run_id')->getValue());
    $this->assertSame(2, $state->get('completed')->getCastedValue());
    $this->assertSame(['first', 'second'], $state->get('steps')->getValue());
    $this->assertSame(['label' => 'Intermediate'], $state->get('details')->getValue());
    $this->assertEquals($host->id(), $state->get('owner')->getValue()->id());
    $this->assertSame('Published', $item->get('outcomes')->get('result')->getValue());
    $this->assertFalse($state->access('view'));
    $this->assertFalse($state->access('edit'));
    $this->assertTrue($state->getFieldDefinition()->isInternal());
    $checklist->setItem('worker', $item);
    $contexts = $this->container->get('checklist.context_collector')->collectRuntimeContexts($checklist);
    $this->assertArrayNotHasKey('item:worker:run_id', $contexts);
    $this->assertSame('Published', $contexts['item:worker:result']->getContextValue());
    $this->assertArrayNotHasKey('state', $contexts['items']->getContextValue()['worker']);
    $progress = $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'worker')['action_state'];
    $this->assertSame(2, $progress['completed']);
    $this->assertArrayNotHasKey('run_id', $progress);
    $this->expectException(\LogicException::class);
    $item->setWorkingState('completed', 3);
  }

  /**
   * Completion clears both field rows and retained typed properties.
   *
   * @dataProvider completionPaths
   */
  public function testCompletionCleanup(string $path): void {
    $this->installEntitySchema('checklist_item');
    $host = $this->host();
    $item = $host->work->checklist->getItem('worker');
    $item->setWorkingState('run_id', 'pending-123')
      ->setWorkingState('steps', ['first'])
      ->setWorkingState('details', ['label' => 'Intermediate'])
      ->setWorkingState('owner', $host)
      ->setOutcome('result', 'Published')
      ->save();
    $run_id = $item->get('state')->get('run_id');
    if ($path === 'helper') {
      $item->setComplete();
    }
    elseif ($path === 'direct') {
      $item->set('status', 'complete');
    }
    else {
      $this->container->get('state')->set('checklist_state_test.complete_in_presave', TRUE);
    }
    $item->save();
    $this->assertNull($run_id->getValue());
    $this->assertTrue($item->get('state')->isEmpty());
    $this->assertSame([], $item->get('state')->getValue());
    $item = $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($item->id());
    $this->assertTrue($item->isComplete());
    $this->assertTrue($item->get('state')->isEmpty());
    $this->assertNull($item->get('state')->get('run_id')->getValue());
    $this->assertSame('Published', $item->get('outcomes')->get('result')->getValue());
  }

  /**
   * Provides completion entry points, including post-entity presave hooks.
   */
  public static function completionPaths(): array {
    return [['helper'], ['direct'], ['presave']];
  }

  /**
   * Handlers must opt in and declare names before setting working values.
   */
  public function testDeclaredStateOnly(): void {
    $this->installEntitySchema('checklist_item');
    $host = $this->host();
    foreach ([['worker', 'unknown'], ['decision', 'run_id']] as [$name, $state]) {
      $item = $host->work->checklist->getItem($name);
      try {
        $item->setWorkingState($state, 'value');
        $this->fail('Undeclared state must not be accepted.');
      }
      catch (\InvalidArgumentException) {
        $this->assertTrue($item->get('state')->isEmpty());
      }
    }
  }

  /**
   * Unsaved working state survives shared tempstore without saving the item.
   */
  public function testTempstoreState(): void {
    $this->installEntitySchema('checklist_item');
    $host = $this->host(FALSE);
    $checklist = $host->work->checklist;
    $item = $checklist->getItem('worker');
    $item->setWorkingState('run_id', 'unsaved-run');
    $repository = $this->container->get('checklist.tempstore_repository');
    $repository->set($checklist);
    $restored = $repository->get($checklist);
    $this->assertTrue($restored->getEntity()->isNew());
    $this->assertTrue($restored->getItem('worker')->isNew());
    $this->assertSame('unsaved-run', $restored->getItem('worker')->get('state')->get('run_id')->getValue());
    $item->setComplete();
    $repository->set($checklist);
    $this->assertTrue($repository->get($checklist)->getItem('worker')->get('state')->isEmpty());
    $this->assertTrue($item->isNew());
  }

  /**
   * The update adds state to old storage without changing saved outcomes.
   */
  public function testUpgrade(): void {
    $state = $this->container->get('state');
    $state->set('checklist_state_test.old_schema', TRUE);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->installEntitySchema('checklist_item');
    $host = $this->host();
    $item = $host->work->checklist->getItem('worker');
    $this->assertFalse($item->hasField('state'));
    $item->setOutcome('result', 'Before upgrade')->save();
    $id = $item->id();
    $state->set('checklist_state_test.old_schema', FALSE);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('module_handler')->loadInclude('checklist', 'install');
    checklist_update_10001();
    checklist_update_10001();
    $item = $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($id);
    $this->assertTrue($item->hasField('state'));
    $this->assertTrue($item->get('state')->isEmpty());
    $this->assertSame('Before upgrade', $item->get('outcomes')->get('result')->getValue());
    $item->setWorkingState('run_id', 'After upgrade')->save();
    $item = $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($id);
    $this->assertSame('After upgrade', $item->get('state')->get('run_id')->getValue());
  }

}
