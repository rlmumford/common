<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistActionState;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests access-filtered item snapshots and side-effect-free action progress.
 *
 * @group checklist
 */
class ChecklistItemReaderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity', 'entity_test',
    'checklist', 'checklist_context_test', 'checklist_resolver_test',
    'checklist_reader_test', 'plugin_reference', 'typed_data', 'typed_data_plus',
    'typed_data_reference', 'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('checklist_item');
    $this->installConfig(['system', 'user']);
    FieldStorageConfig::create([
      'field_name' => 'work',
      'entity_type' => 'user',
      'type' => 'checklist',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create(['field_name' => 'work', 'entity_type' => 'user', 'bundle' => 'user'])->save();
    $admin = User::create(['name' => 'Admin']);
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);
  }

  /**
   * Creates a host with context-driven progress, a hidden item and a decision.
   */
  protected function host(array $configuration = [], bool $save = TRUE, string $name = 'Host'): User {
    $default_items = [
      'progress' => [
        'title' => 'Process content',
        'handler' => 'reader_progress',
        'handler_configuration' => $configuration + ['context_mapping' => ['value' => 'checklist:entity.name.value']],
      ],
      'hidden' => [
        'title' => 'Hidden work',
        'handler' => 'reader_progress',
        'handler_configuration' => [
          'fail_on_read' => TRUE,
          'resource_key' => $configuration['hidden_resource_key'] ?? NULL,
          'context_mapping' => ['value' => 'checklist:entity.name.value'],
        ],
      ],
      'decision' => [
        'title' => 'Review',
        'handler' => 'decision',
        'handler_configuration' => ['question' => 'Approve?', 'options' => ['yes' => ['label' => 'Yes']]],
      ],
    ];
    if (!empty($configuration['add_shared_resource'])) {
      $default_items['resource_peer'] = [
        'title' => 'Review resource',
        'handler' => 'reader_progress',
        'handler_configuration' => [
          'resource_key' => $configuration['resource_key'],
          'resource_label' => 'Latest resource',
          'context_mapping' => ['value' => 'checklist:entity.name.value'],
        ],
      ];
    }
    $host = User::create([
      'name' => $name,
      'status' => 1,
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => $default_items,
        ],
      ],
    ]);
    if ($save) {
      $host->save();
    }
    return $host;
  }

  /**
   * Reads expose safe progress and current contexts without changing work.
   */
  public function testProgressSnapshots(): void {
    $host = $this->host();
    $reader = $this->container->get('checklist.item_reader');
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $item = $checklist->getItem('progress');
    $before = $item->toArray();
    $this->container->get('state')->set('checklist_reader_test.progress', ['secret' => 'Never return this']);
    $snapshot = $reader->read($host, 'work', 0, 'progress');
    $this->assertSame([
      'name' => 'progress',
      'title' => 'Process content',
      'status' => 'incomplete',
      'contexts_available' => TRUE,
      'applicable' => TRUE,
      'required' => TRUE,
      'actionable' => TRUE,
      'action_state' => [
        'stage' => 'running',
        'message' => 'Processing Host',
        'completed' => 2,
        'total' => 5,
        'updated_at' => 1234567890,
        'input_required' => FALSE,
      ],
    ], $snapshot);
    $this->assertSame($before, $item->toArray());
    $this->assertTrue($item->isNew());
    $this->assertFalse($item->isAttempted());
    $this->assertFalse($this->container->get('checklist.tempstore_repository')->has($checklist));
    $host->set('name', 'Changed in memory');
    $this->assertSame('Processing Changed in memory', $reader->read($host, 'work', 0, 'progress')['action_state']['message']);
    $this->container->get('state')->set('checklist_reader_test.progress', [
      'stage' => 'waiting',
      'completed' => 4,
      'updated_at' => 1234567899,
      'input_required' => TRUE,
    ]);
    $state = $reader->read($host, 'work', 0, 'progress')['action_state'];
    $this->assertSame('waiting', $state['stage']);
    $this->assertSame(4, $state['completed']);
    $this->assertSame(1234567899, $state['updated_at']);
    $this->assertTrue($state['input_required']);
  }

  /**
   * Hidden items are absent and cannot be distinguished from missing names.
   */
  public function testVisibility(): void {
    $host = $this->host();
    $reader = $this->container->get('checklist.item_reader');
    $items = $reader->readItems($host, 'work');
    $this->assertSame(['progress', 'decision'], array_keys($items));
    $this->assertNull($items['decision']['action_state']);
    foreach (['hidden', 'missing'] as $name) {
      try {
        $reader->read($host, 'work', 0, $name);
        $this->fail('Hidden and missing items must not be readable.');
      }
      catch (NotFoundHttpException $e) {
        $this->assertSame('Checklist item not found.', $e->getMessage());
      }
    }
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $dispatcher = $this->container->get('checklist.action_operation_dispatcher');
    $this->assertSame([], $dispatcher->discover($checklist, 'hidden'));
    $this->expectException(\DomainException::class);
    $dispatcher->execute($checklist, 'hidden', 'continue', []);
  }

  /**
   * View permission permits progress reads but grants no execution authority.
   */
  public function testReadOnlyViewer(): void {
    $host = $this->host();
    Role::create(['id' => 'viewer', 'label' => 'Viewer', 'permissions' => ['access user profiles']])->save();
    $viewer = User::create(['name' => 'Viewer', 'roles' => ['viewer']]);
    $viewer->save();
    $this->container->get('current_user')->setAccount($viewer);
    $snapshot = $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'progress');
    $this->assertSame('incomplete', $snapshot['status']);
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $dispatcher = $this->container->get('checklist.action_operation_dispatcher');
    $this->assertFalse($checklist->getItem('progress')->access('view'));
    $this->assertSame([], $dispatcher->discover($checklist, 'progress'));
    $this->expectException(AccessDeniedHttpException::class);
    $dispatcher->execute($checklist, 'progress', 'continue', []);
  }

  /**
   * Missing runtime values clear old progress; unknown gates stay conservative.
   */
  public function testMissingContexts(): void {
    $host = $this->host(['context_mapping' => ['value' => 'item:decision:decision']]);
    $reader = $this->container->get('checklist.item_reader');
    $source = $this->container->get('checklist.resolver')->resolve($host, 'work')->getItem('decision');
    $source->setOutcome('decision', 'yes');
    $this->assertNotNull($reader->read($host, 'work', 0, 'progress')['action_state']);
    $source->setOutcome('decision', NULL);
    $snapshot = $reader->read($host, 'work', 0, 'progress');
    $this->assertFalse($snapshot['contexts_available']);
    $this->assertNull($snapshot['applicable']);
    $this->assertTrue($snapshot['required']);
    $this->assertFalse($snapshot['actionable']);
    $this->assertNull($snapshot['action_state']);
  }

  /**
   * Terminal state and false applicability prevent actionable snapshots.
   */
  public function testStatusAndGates(): void {
    $host = $this->host(['conditions' => ['applicability' => ['id' => 'condition_constant:false']]]);
    $reader = $this->container->get('checklist.item_reader');
    $snapshot = $reader->read($host, 'work', 0, 'progress');
    $this->assertFalse($snapshot['applicable']);
    $this->assertFalse($snapshot['actionable']);
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $item = $checklist->getItem('decision');
    foreach (['complete', 'failed', 'na'] as $status) {
      $item->set('status', $status);
      $snapshot = $reader->read($host, 'work', 0, 'decision');
      $this->assertSame($status, $snapshot['status']);
      $this->assertFalse($snapshot['actionable']);
    }
  }

  /**
   * Item resources use current contexts, gates, and item visibility.
   */
  public function testActionResources(): void {
    $host = $this->host([
      'resource_key' => 'shared-case-file',
      'resource_label' => 'Case file',
      'resource_weight' => 10,
      'add_shared_resource' => TRUE,
    ]);
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $resources = $this->container->get('checklist.action_resource_collector')->collect($checklist);
    $this->assertSame(['shared-case-file'], array_keys($resources));
    $this->assertSame(['progress', 'resource_peer'], $resources['shared-case-file']['owners']);
    $this->assertSame('Latest resource', $resources['shared-case-file']['resource']->getLabel());
    $this->assertSame(
      ['#plain_text' => 'Context value: Host'],
      $resources['shared-case-file']['resource']->getContent()
    );

    // A false actionability gate removes unfinished resources.
    $gated_host = $this->host([
      'resource_key' => 'gated',
      'conditions' => ['actionability' => ['id' => 'condition_constant:false']],
    ], TRUE, 'Gated host');
    $gated_checklist = $this->container->get('checklist.resolver')->resolve($gated_host, 'work');
    $this->assertSame([], $this->container->get('checklist.action_resource_collector')->collect($gated_checklist));

    // Terminal items retain their resource even when the actionability gate is
    // now false, so completed/failed work remains available for review.
    $gated_checklist->getItem('progress')->setComplete();
    $terminal_resources = $this->container->get('checklist.action_resource_collector')->collect($gated_checklist);
    $this->assertArrayHasKey('gated', $terminal_resources);
    $gated_checklist->getItem('progress')->setFailed();
    $failed_resources = $this->container->get('checklist.action_resource_collector')->collect($gated_checklist);
    $this->assertArrayHasKey('gated', $failed_resources);

    // Hidden items cannot leak their resource pane content.
    $hidden_host = $this->host(['hidden_resource_key' => 'private'], TRUE, 'Hidden host');
    $hidden_checklist = $this->container->get('checklist.resolver')->resolve($hidden_host, 'work');
    $this->container->get('state')->set('checklist_reader_test.grant_item_access', FALSE);
    $this->assertArrayNotHasKey('private', $this->container->get('checklist.action_resource_collector')->collect($hidden_checklist));
  }

  /**
   * Field access is checked before evaluating plugin progress.
   */
  public function testDeniedField(): void {
    $host = $this->host(['fail_on_read' => TRUE]);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['view']]);
    $this->expectException(AccessDeniedHttpException::class);
    $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'progress');
  }

  /**
   * Field edit denial also blocks action-operation discovery and dispatch.
   */
  public function testDeniedFieldOperation(): void {
    $host = $this->host();
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work');
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => ['edit']]);
    // An item-level grant cannot override the containing field's denial.
    $this->container->get('state')->set('checklist_reader_test.grant_item_access', TRUE);
    $dispatcher = $this->container->get('checklist.action_operation_dispatcher');
    $this->assertSame([], $dispatcher->discover($checklist, 'progress'));
    $this->expectException(\DomainException::class);
    $dispatcher->execute($checklist, 'progress', 'continue', []);
  }

  /**
   * Invalid configuration surfaces as an error, never false readiness.
   */
  public function testInvalidContext(): void {
    $host = $this->host(['context_mapping' => ['value' => 'missing:source']]);
    $this->expectException(ContextException::class);
    $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'progress');
  }

  /**
   * Unsaved hosts can be inspected without being saved by the reader.
   */
  public function testUnsavedHost(): void {
    $host = $this->host([], FALSE);
    $snapshot = $this->container->get('checklist.item_reader')->read($host, 'work', 0, 'progress');
    $this->assertSame('Processing Host', $snapshot['action_state']['message']);
    $this->assertTrue($host->isNew());
    $this->assertNull($host->id());
  }

  /**
   * Progress projections reject impossible counts and negative timestamps.
   */
  public function testInvalidProgress(): void {
    foreach ([[-1, 5, 1], [2, -1, 1], [6, 5, 1], [2, 5, -1]] as [$completed, $total, $updated]) {
      try {
        new ChecklistActionState(completed: $completed, total: $total, updatedAt: $updated);
        $this->fail('Invalid progress must be rejected.');
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
    $this->assertSame([
      'stage' => NULL,
      'message' => NULL,
      'completed' => NULL,
      'total' => NULL,
      'updated_at' => NULL,
      'input_required' => FALSE,
    ], (new ChecklistActionState())->toArray());
  }

}
