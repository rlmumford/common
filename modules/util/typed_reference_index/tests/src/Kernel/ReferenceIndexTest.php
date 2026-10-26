<?php

namespace Drupal\Tests\typed_reference_index\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests a generic, disposable index without tasks, documents or checklists.
 *
 * @group typed_reference_index
 */
class ReferenceIndexTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'entity_test', 'typed_data',
    'typed_data_plus', 'typed_data_reference', 'typed_reference_index',
    'typed_reference_index_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installSchema('typed_reference_index', ['typed_reference_index']);
    FieldStorageConfig::create([
      'field_name' => 'references',
      'entity_type' => 'entity_test',
      'type' => 'typed_data_reference',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'references',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * A cache rebuild exactly recovers current source values and no history.
   */
  public function testCurrentValuesAndRebuild(): void {
    $target = EntityTest::create(['name' => 'Referenced entity']);
    $target->save();
    $other = EntityTest::create(['name' => 'Other entity']);
    $other->save();
    $source = EntityTest::create([
      'name' => 'Source',
      'references' => [['key' => 'packet', 'blob' => ['records' => [$target->id(), $other->id()]]]],
    ]);
    $source->save();
    $index = $this->container->get('typed_reference_index.index');
    $lookup = fn($id) => $index->referringSources('entity_test', (string) $id, 'entity_test', 'references')->execute()->fetchCol();
    $this->assertEquals([$source->id()], $lookup($target->id()));
    $this->assertEquals([$source->id()], $lookup($other->id()));
    $source->set('references', [['key' => 'packet.records[0]', 'value' => $other->id()]])->save();
    $this->assertSame([], $lookup($target->id()));
    $index->clear();
    $this->assertSame([], $lookup($other->id()));
    $after = NULL;
    $batches = 0;
    do {
      $after = $index->rebuild('entity_test', $after, 1);
      $batches++;
    } while ($after !== NULL);
    $this->assertSame(4, $batches);
    $this->assertEquals([$source->id()], $lookup($other->id()));
    $state = $this->container->get('state');
    $state->set('typed_reference_index.rebuild', []);
    $this->container->get('module_handler')->invoke('typed_reference_index', 'cron');
    $this->assertNotNull($state->get('typed_reference_index.rebuild'));
    $this->container->get('module_handler')->invoke('typed_reference_index', 'cron');
    $this->assertNull($state->get('typed_reference_index.rebuild'));
    $this->assertEquals([$source->id()], $lookup($other->id()));
    $transaction = $this->container->get('database')->startTransaction();
    $source->set('references', [])->save();
    $this->assertSame([], $lookup($other->id()));
    $transaction->rollBack();
    unset($transaction);
    $this->assertEquals([$source->id()], $lookup($other->id()));
    $source->delete();
    $this->assertSame([], $lookup($other->id()));
  }

}
