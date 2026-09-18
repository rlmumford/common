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
 * Tests entity-based checklist resolution, host isolation and field access.
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
      $checklist = $resolver->resolve($host, $field, $delta, 'update');
      $this->assertSame($key, $checklist->getKey());
      $item = $checklist->getItem('decision');
      $this->assertSame($title, $item->get('title')->value);
      $this->assertSame($checklist, $item->get('checklist')->checklist);
      $item->getHandler()->choose('yes');
    }
    $this->container->get('entity_type.manager')->getStorage('checklist_item')->resetCache();
    $host = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $ids = [];
    foreach ([['work', 0], ['multiple', 0], ['multiple', 1]] as [$field, $delta]) {
      $checklist = $resolver->resolve($host, $field, $delta);
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
    $first = $resolver->resolve($host, 'work');
    $second = $resolver->resolve($other, 'work');
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
      [$host, 'missing_field', 0],
      [$host, 'name', 0],
      [$host, 'work', -1],
      [$host, 'work', 1],
      [$host, 'multiple', 2],
    ] as $address) {
      try {
        $resolver->resolve(...$address);
        $this->fail('An invalid checklist address must be rejected.');
      }
      catch (NotFoundHttpException) {
        $this->addToAssertionCount(1);
      }
    }
    $host->work = $this->value('entity_context_test', 'Wrong host type');
    $host->save();
    $this->expectException(NotFoundHttpException::class);
    $resolver->resolve($host, 'work');
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
      $this->assertSame('work', $resolver->resolve($host, 'work')->getKey());
    }
    $this->expectException(AccessDeniedHttpException::class);
    $resolver->resolve($host, 'work', 0, $operation);
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
    $this->container->get('checklist.resolver')->resolve($host, 'work', 0, 'update');
  }

  /**
   * The supplied object and its unsaved checklist edits remain authoritative.
   */
  public function testSuppliedState(): void {
    $host = $this->host();
    $resolver = $this->container->get('checklist.resolver');
    $checklist = $resolver->resolve($host, 'work');
    $item = $checklist->getItem('decision');
    $item->setFailed();
    $host->set('name', 'Unsaved name');
    $this->assertSame($checklist, $resolver->resolve($host, 'work'));
    $this->assertSame($host, $checklist->getEntity());
    $this->assertSame('Unsaved name', $checklist->getEntity()->get('name')->value);
    $this->assertTrue($checklist->getItem('decision')->isFailed());
    $this->assertSame($checklist, $item->get('checklist')->checklist);
    $this->assertTrue($item->isNew());
  }

  /**
   * A fresh entity graph is not silently replaced with a tempstore snapshot.
   */
  public function testNoImplicitTempstore(): void {
    $host = $this->host();
    $checklist = $host->work->checklist;
    $checklist->getItem('decision')->setFailed();
    $repository = $this->container->get('checklist.tempstore_repository');
    $repository->set($checklist);
    $fresh = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $resolved = $this->container->get('checklist.resolver')->resolve($fresh, 'work');
    $this->assertSame($fresh, $resolved->getEntity());
    $this->assertTrue($resolved->getItem('decision')->isIncomplete());
    $this->assertSame($resolved, $resolved->getItem('decision')->get('checklist')->checklist);
    $this->assertTrue($repository->get($resolved)->getItem('decision')->isFailed());
    // Serialization drops computed fields. A workspace adapter attaches its
    // restored checklist before supplying the entity to resolve().
    $workspace = $repository->get($resolved);
    $workspace->getEntity()->get('work')->first()->get('checklist')->setValue($workspace, FALSE);
    $this->assertSame($workspace, $this->container->get('checklist.resolver')->resolve($workspace->getEntity(), 'work'));
  }

  /**
   * Host access cannot authorize a checklist attached from another graph.
   */
  public function testMismatchedAttachedHost(): void {
    $host = $this->host();
    $resolver = $this->container->get('checklist.resolver');
    $original = $resolver->resolve($host, 'work');
    $copy = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $copy->get('work')->first()->get('checklist')->setValue($original, FALSE);
    $this->expectException(\InvalidArgumentException::class);
    $resolver->resolve($copy, 'work');
  }

  /**
   * Unsaved hosts are resolvable without creating entities or checklist items.
   */
  public function testUnsavedEntity(): void {
    $host = User::create([
      'name' => 'Unsaved host',
      'work' => $this->value('context_test', 'Draft checklist'),
    ]);
    $checklist = $this->container->get('checklist.resolver')->resolve($host, 'work', 0, 'update');
    $this->assertSame($host, $checklist->getEntity());
    $this->assertNull($host->id());
    $this->assertTrue($host->isNew());
    $item = $checklist->getItem('decision');
    $this->assertSame('Draft checklist', $item->get('title')->value);
    $this->assertTrue($item->isNew());
    $this->assertSame($host, $item->get('checklist')->entity);
    $this->assertSame($checklist, $item->get('checklist')->checklist);
  }

  /**
   * UUID-keyed tempstore works before save and keeps its address afterwards.
   */
  public function testUnsavedTempstoreIdentity(): void {
    $host = User::create([
      'name' => 'Draft host',
      'work' => $this->value('context_test', 'Draft checklist'),
    ]);
    $other = User::create([
      'name' => 'Other draft',
      'work' => $this->value('context_test', 'Other checklist'),
    ]);
    $resolver = $this->container->get('checklist.resolver');
    $repository = $this->container->get('checklist.tempstore_repository');
    $checklist = $resolver->resolve($host, 'work', 0, 'update');
    $checklist->getItem('decision')->setFailed();
    $uuid = $host->uuid();
    $this->assertNotEmpty($uuid);
    $this->assertNotSame($uuid, $other->uuid());
    $this->assertNull($host->id());
    $repository->set($checklist);
    $this->assertTrue($repository->has($checklist));
    $this->assertFalse($repository->has($resolver->resolve($other, 'work')));
    $restored = $repository->get($checklist);
    $this->assertNull($restored->getEntity()->id());
    $this->assertTrue($restored->getEntity()->isNew());
    $this->assertSame($uuid, $restored->getEntity()->uuid());
    $this->assertTrue($restored->getItem('decision')->isFailed());

    $host->save();
    $this->assertSame($uuid, $host->uuid());
    $fresh = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($host->id());
    $saved_checklist = $resolver->resolve($fresh, 'work');
    $this->assertTrue($repository->has($saved_checklist));
    $this->assertTrue($repository->get($saved_checklist)->getItem('decision')->isFailed());
    // Stable addressing does not rewrite an already serialized host snapshot.
    $this->assertNull($repository->get($saved_checklist)->getEntity()->id());
    $repository->set($checklist);
    $this->assertEquals($host->id(), $repository->get($saved_checklist)->getEntity()->id());
    $repository->delete($saved_checklist);
    $this->assertFalse($repository->has($checklist));
  }

  /**
   * Unsaved hosts require creation access, not an assumed edit permission.
   */
  public function testDeniedCreation(): void {
    $other = User::create(['name' => 'Unprivileged']);
    $other->save();
    $this->container->get('current_user')->setAccount($other);
    $host = EntityTest::create(['name' => 'Unsaved', 'work' => $this->value('entity_context_test', 'Draft')]);
    $this->expectException(AccessDeniedHttpException::class);
    $this->container->get('checklist.resolver')->resolve($host, 'work', 0, 'update');
  }

}
