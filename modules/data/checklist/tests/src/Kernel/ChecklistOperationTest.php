<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests shared operation dispatch with decisions and context-aware handlers.
 *
 * @group checklist
 */
class ChecklistOperationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'checklist', 'checklist_context_test', 'plugin_reference', 'typed_data',
    'typed_data_plus', 'typed_data_reference', 'typed_data_context_assignment',
    'inline_entity_form',
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
  }

  /**
   * Creates a checklist whose operation consumes another item's outcome.
   */
  protected function checklist(array $configuration = [], string $handler = 'operation_consumer'): ChecklistInterface {
    $owner = User::create([
      'name' => 'Owner',
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'source' => ['title' => 'Source', 'handler' => 'context_producer', 'handler_configuration' => []],
            'operation' => [
              'title' => 'Operation',
              'handler' => $handler,
              'handler_configuration' => $configuration + [
                'stay_incomplete' => TRUE,
                'context_mapping' => [
                  'value' => 'item:source:value',
                  'optional' => 'item:source:details.label',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $owner->save();
    $this->container->get('current_user')->setAccount($owner);
    return $owner->work->checklist;
  }

  /**
   * Discovery and execution refresh outcomes, clearing values that disappear.
   */
  public function testFreshContexts(): void {
    $checklist = $this->checklist();
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    $source = $checklist->getItem('source');
    $source->setOutcome('value', 'First');
    $source->setOutcome('details', ['label' => 'Optional']);
    $this->assertSame('First', $dispatcher->discover($checklist, 'operation')['read']['label']);
    $source->setOutcome('value', 'Second');
    $source->setOutcome('details', NULL);
    $this->assertSame(['value' => 'Second', 'optional' => NULL], $dispatcher->execute($checklist, 'operation', 'read', []));
    $source->setOutcome('value', NULL);
    $this->assertSame([], $dispatcher->discover($checklist, 'operation'));
    try {
      $dispatcher->execute($checklist, 'operation', 'read', []);
      $this->fail('A removed required context must block execution.');
    }
    catch (\DomainException) {
      $this->assertSame([['Second', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
      $this->assertFalse($checklist->getItem('operation')->getHandler()->getContext('value')->hasContextValue());
    }
  }

  /**
   * Gates changed since discovery block execution before invoking a handler.
   *
   * @dataProvider gates
   */
  public function testChangedGate(string $gate): void {
    $checklist = $this->checklist([
      'conditions' => [$gate => ['id' => 'condition_string', 'condition_string' => "checklist.name.value == 'Owner'"]],
    ]);
    $checklist->getItem('source')->setOutcome('value', 'Ready');
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    $this->assertArrayHasKey('read', $dispatcher->discover($checklist, 'operation'));
    $checklist->getEntity()->set('name', 'Changed');
    $this->assertSame([], $dispatcher->discover($checklist, 'operation'));
    try {
      $dispatcher->execute($checklist, 'operation', 'read', []);
      $this->fail('Changed item gates must block execution.');
    }
    catch (\DomainException) {
      $this->assertNull($this->container->get('state')->get('checklist_context_test.runs'));
    }
  }

  /**
   * Provides execution gates.
   */
  public static function gates(): array {
    return [['applicability'], ['actionability']];
  }

  /**
   * Unknown applicability blocks execution even with valid handler contexts.
   */
  public function testUnknownApplicability(): void {
    $checklist = $this->checklist([
      'conditions' => [
        'applicability' => [
          'id' => 'user_role',
          'roles' => ['authenticated'],
          'context_mapping' => ['user' => 'item:source:user'],
        ],
      ],
    ]);
    $checklist->getItem('source')->setOutcome('value', 'Ready');
    $this->assertNull($checklist->getItem('operation')->isApplicable());
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    $this->assertSame([], $dispatcher->discover($checklist, 'operation'));
    $this->expectException(\DomainException::class);
    $dispatcher->execute($checklist, 'operation', 'read', []);
  }

  /**
   * An account change after discovery cannot authorize a later invocation.
   */
  public function testChangedAccount(): void {
    $checklist = $this->checklist();
    $checklist->getItem('source')->setOutcome('value', 'Ready');
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    $this->assertNotEmpty($dispatcher->discover($checklist, 'operation'));
    $other = User::create(['name' => 'Other']);
    $other->save();
    $this->container->get('current_user')->setAccount($other);
    $this->assertSame([], $dispatcher->discover($checklist, 'operation'));
    // Access is checked before even looking up an item name.
    $this->assertSame([], $dispatcher->discover($checklist, 'missing'));
    try {
      $dispatcher->execute($checklist, 'operation', 'read', []);
      $this->fail('Changed access must block execution.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertNull($this->container->get('state')->get('checklist_context_test.runs'));
      $this->assertSame($other->id(), $this->container->get('current_user')->id());
    }
  }

  /**
   * Only incomplete items support normal operations, never implicit retries.
   *
   * @dataProvider finishedStates
   */
  public function testFinishedItem(string $status): void {
    $checklist = $this->checklist();
    $checklist->getItem('source')->setOutcome('value', 'Ready');
    $checklist->getItem('operation')->set('status', $status);
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    $this->assertSame([], $dispatcher->discover($checklist, 'operation'));
    $this->expectException(\DomainException::class);
    $dispatcher->execute($checklist, 'operation', 'read', []);
  }

  /**
   * Provides statuses which must not execute ordinary operations.
   */
  public static function finishedStates(): array {
    return [
      [ChecklistItemInterface::STATUS_COMPLETE],
      [ChecklistItemInterface::STATUS_FAILED],
      [ChecklistItemInterface::STATUS_NA],
    ];
  }

  /**
   * Unknown names and operations that disappeared are not dispatched.
   */
  public function testUnavailableOperations(): void {
    $checklist = $this->checklist();
    $source = $checklist->getItem('source');
    $source->setOutcome('value', 'Ready');
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    $this->assertNotEmpty($dispatcher->discover($checklist, 'operation'));
    $source->setOutcome('value', 'Hidden');
    foreach ([['missing', 'read'], ['operation', 'missing'], ['operation', 'read']] as [$item, $operation]) {
      try {
        $dispatcher->execute($checklist, $item, $operation, []);
        $this->fail('Missing items or operations must not execute.');
      }
      catch (\InvalidArgumentException) {
        $this->assertNull($this->container->get('state')->get('checklist_context_test.runs'));
      }
    }
    $this->assertSame([], $dispatcher->discover($checklist, 'source'));
    $this->expectException(\DomainException::class);
    $dispatcher->execute($checklist, 'source', 'read', []);
  }

  /**
   * Invalid configuration remains an error rather than an empty operation list.
   */
  public function testInvalidMapping(): void {
    $checklist = $this->checklist(['context_mapping' => ['value' => 'missing:source']]);
    $this->expectException(ContextException::class);
    $this->container->get('checklist.operation_dispatcher')->discover($checklist, 'operation');
  }

  /**
   * Real decisions validate parameters and save outcomes through the service.
   */
  public function testDecisionPersistence(): void {
    $checklist = $this->checklist([
      'question' => 'Approve?',
      'options' => ['approve' => ['label' => 'Approve', 'require_reason' => TRUE]],
    ], 'decision');
    $dispatcher = $this->container->get('checklist.operation_dispatcher');
    try {
      $dispatcher->execute($checklist, 'operation', 'choose', ['choice' => 'approve']);
      $this->fail('The dispatcher must retain handler validation.');
    }
    catch (\InvalidArgumentException) {
      $this->assertTrue($checklist->getItem('operation')->isNew());
      $this->assertTrue($checklist->getItem('operation')->isIncomplete());
    }
    $result = $dispatcher->execute($checklist, 'operation', 'choose', ['choice' => 'approve', 'reason' => 'Checked']);
    $this->assertSame(['decision' => 'approve', 'reason' => 'Checked'], $result);
    $item = $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($checklist->getItem('operation')->id());
    $this->assertTrue($item->isComplete());
    $this->assertSame('approve', $item->get('outcomes')->get('decision')->getValue());
    $this->assertSame([], $dispatcher->discover($checklist, 'operation'));
  }

}
