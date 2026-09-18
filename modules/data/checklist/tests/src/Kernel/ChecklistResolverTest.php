<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests persisted checklist addressing, host isolation and field access.
 *
 * @group checklist
 */
class ChecklistResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity',
    'entity_test', 'checklist', 'checklist_context_test', 'checklist_resolver_test',
    'plugin_reference', 'typed_data', 'typed_data_plus', 'typed_data_reference',
    'typed_data_context_assignment', 'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('checklist_item');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['system', 'user']);
    foreach (['user', 'entity_test'] as $entity_type) {
      foreach (['work' => 1, 'multiple' => -1] as $field_name => $cardinality) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => $entity_type,
          'type' => 'checklist',
          'cardinality' => $cardinality,
        ])->save();
        FieldConfig::create(['field_name' => $field_name, 'entity_type' => $entity_type, 'bundle' => $entity_type])->save();
      }
    }
    $admin = User::create(['name' => 'Admin']);
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);
  }

  /**
   * Builds field configuration with one decision item.
   */
  protected function value(string $type, string $label): array {
    return [
      'id' => $type,
      'configuration' => [
        'default_items' => [
          'decision' => [
            'title' => $label,
            'handler' => 'decision',
            'handler_configuration' => [
              'question' => $label,
              'options' => ['yes' => ['label' => 'Yes'], 'no' => ['label' => 'No']],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Creates a saved host with both single and multi-value checklist fields.
   */
  protected function host(): FieldableEntityInterface {
    $host = User::create([
      'name' => 'Host',
      'work' => $this->value('context_test', 'Single'),
      'multiple' => [
        $this->value('context_test', 'First'),
        $this->value('context_test', 'Second'),
      ],
    ]);
    $host->save();
    return $host;
  }

  /**
   * Field names and deltas address independent persisted item sets.
   */
  public function testFieldAndDeltaIsolation(): void {
    $host = $this->host();
    $resolver = $this->container->get('checklist.resolver');
    $locations = [
      ['work', 0, 'work', 'Single'],
      ['multiple', 0, 'multiple:0', 'First'],
      ['multiple', 1, 'multiple:1', 'Second'],
    ];
    foreach ($locations as [$field, $delta, $key, $title]) {
      $checklist = $resolver->resolveStored('user', $host->id(), $field, $delta, 'update');
      $this->assertSame($key, $checklist->getKey());
      $item = $checklist->getItem('decision');
      $this->assertSame($title, $item->get('title')->value);
      $this->assertSame($checklist, $item->get('checklist')->checklist);
      $item->getHandler()->choose('yes');
    }
    $ids = [];
    foreach ([['work', 0], ['multiple', 0], ['multiple', 1]] as [$field, $delta]) {
      $checklist = $resolver->resolveStored('user', $host->id(), $field, $delta);
      $item = $checklist->getItem('decision');
      $ids[] = $item->id();
      $this->assertTrue($item->isComplete());
      $this->assertSame('yes', $item->get('outcomes')->get('decision')->getValue());
    }
    $this->assertCount(3, array_unique($ids));
  }

  /**
   * Matching IDs, fields and names on different host types never mix items.
   */
  public function testHostTypeIsolation(): void {
    $host = $this->host();
    $other = EntityTest::create([
      'id' => $host->id(),
      'name' => 'Other host type',
      'work' => $this->value('entity_context_test', 'Other decision'),
    ]);
    $other->save();
    $this->assertEquals($host->id(), $other->id());
    $first = $host->work->checklist;
    $first->getItem('decision')->getHandler()->choose('yes');
    // Test normal checklist loading too, not only the new resolver.
    $second = $other->work->checklist;
    $this->assertTrue($second->getItem('decision')->isNew());
    $second->getItem('decision')->getHandler()->choose('no');
    $resolver = $this->container->get('checklist.resolver');
    $first = $resolver->resolveStored('user', $host->id(), 'work');
    $second = $resolver->resolveStored('entity_test', $other->id(), 'work');
    $this->assertSame('yes', $first->getItem('decision')->get('outcomes')->get('decision')->getValue());
    $this->assertSame('no', $second->getItem('decision')->get('outcomes')->get('decision')->getValue());
    $this->assertNotEquals($first->getItem('decision')->id(), $second->getItem('decision')->id());
  }

  /**
   * Invalid fields, deltas and host types cannot be treated as checklists.
   */
  public function testInvalidAddresses(): void {
    $host = $this->host();
    $resolver = $this->container->get('checklist.resolver');
    foreach ([
      ['missing_type', $host->id(), 'work', 0],
      ['user', '99999', 'work', 0],
      ['user', $host->id(), 'missing_field', 0],
      ['user', $host->id(), 'name', 0],
      ['user', $host->id(), 'work', -1],
      ['user', $host->id(), 'work', 1],
      ['user', $host->id(), 'multiple', 2],
    ] as $address) {
      try {
        $resolver->resolveStored(...$address);
        $this->fail('An invalid checklist address must be rejected.');
      }
      catch (NotFoundHttpException) {
        $this->addToAssertionCount(1);
      }
    }
    $host->work = $this->value('entity_context_test', 'Wrong host type');
    $host->save();
    $this->expectException(NotFoundHttpException::class);
    $resolver->resolveStored('user', $host->id(), 'work');
  }

  /**
   * Field access is enforced even when the account can edit the host.
   *
   * @dataProvider fieldAccess
   */
  public function testFieldAccess(string $denied, string $operation): void {
    $host = $this->host();
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['work' => [$denied]]);
    $resolver = $this->container->get('checklist.resolver');
    if ($denied === 'edit') {
      $this->assertSame('work', $resolver->resolveStored('user', $host->id(), 'work')->getKey());
    }
    $this->expectException(AccessDeniedHttpException::class);
    $resolver->resolveStored('user', $host->id(), 'work', 0, $operation);
  }

  /**
   * Provides view and edit access requirements.
   */
  public static function fieldAccess(): array {
    return [['view', 'view'], ['view', 'update'], ['edit', 'update']];
  }

  /**
   * Entity permissions cannot be bypassed by knowing the field address.
   */
  public function testHostAccess(): void {
    $host = $this->host();
    $other = User::create(['name' => 'Other']);
    $other->save();
    $this->container->get('current_user')->setAccount($other);
    $this->expectException(AccessDeniedHttpException::class);
    $this->container->get('checklist.resolver')->resolveStored('user', $host->id(), 'work', 0, 'update');
  }

  /**
   * Stored resolution does not inherit stale in-memory or form tempstore state.
   */
  public function testStoredState(): void {
    $host = $this->host();
    $checklist = $host->work->checklist;
    $item = $checklist->getItem('decision');
    $item->save();
    $item->setFailed();
    $this->container->get('checklist.tempstore_repository')->set($checklist);
    $host->set('name', 'Unsaved name');
    $resolver = $this->container->get('checklist.resolver');
    $stored = $resolver->resolveStored('user', $host->id(), 'work');
    $this->assertSame('Host', $stored->getEntity()->get('name')->value);
    $stored_item = $stored->getItem('decision');
    $this->assertTrue($stored_item->isIncomplete());
    $this->assertSame($stored, $stored_item->get('checklist')->checklist);
    $this->assertTrue($this->container->get('checklist.tempstore_repository')->get($stored)->getItem('decision')->isFailed());
  }

}
