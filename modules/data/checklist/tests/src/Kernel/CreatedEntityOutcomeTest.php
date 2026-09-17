<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests entity creation outcomes consumed by later checklist items.
 *
 * @group checklist
 */
class CreatedEntityOutcomeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'entity', 'entity_test',
    'checklist', 'checklist_context_test', 'plugin_reference', 'typed_data',
    'typed_data_plus', 'typed_data_reference', 'typed_data_context_assignment',
    'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'checklist_item', 'entity_test'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installConfig(['system', 'user']);
    entity_test_create_bundle('record', 'Record');
  }

  /**
   * Builds a checklist using the real create-entity plugin.
   */
  protected function checklist(bool $interactive = FALSE, string $bundle = 'record'): ChecklistInterface {
    $owner = User::create(['name' => 'Owner', 'mail' => 'owner@example.com']);
    $owner->save();
    $type = $this->container->get('plugin.manager.checklist_type')->createInstance('context_test', [
      'default_items' => [
        'source' => [
          'title' => 'Create a record',
          'handler' => 'create_entity:entity_test',
          'handler_configuration' => ['bundle' => $bundle, 'show_form' => $interactive],
        ],
        'consumer' => [
          'title' => 'Consume the record',
          'handler' => 'context_consumer',
          'handler_configuration' => ['context_mapping' => ['value' => 'item:source:entity_test.type.value']],
        ],
      ],
    ]);
    return $type->getChecklist($owner, 'checklist');
  }

  /**
   * Automatic creation publishes outcomes in the same pass and after reload.
   */
  public function testAutomaticCreation(): void {
    $checklist = $this->checklist();
    $this->assertTrue($checklist->process());
    $this->assertSame([['record', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertSame('auto', $checklist->getItem('source')->completion_method->value);
    $created_id = $checklist->getItem('source')->outcomes->get('entity_test')->getValue()->id();
    $this->assertSame('record', EntityTest::load($created_id)->bundle());

    // Rebuild from storage and run the consumer again, without creating again.
    $this->container->get('entity_type.manager')->getStorage('checklist_item')->resetCache();
    $checklist = $checklist->getType()->getChecklist($checklist->getEntity(), 'checklist');
    $checklist->getItem('consumer')->setIncomplete()->save();
    $this->container->get('state')->delete('checklist_context_test.runs');
    $this->assertTrue($checklist->process());
    $this->assertSame([['record', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertEquals($created_id, $checklist->getItem('source')->outcomes->get('entity_test')->getValue()->id());
    $this->assertCount(1, EntityTest::loadMultiple());
  }

  /**
   * The actual action-form submission publishes the same named entity outcome.
   */
  public function testInteractiveCreation(): void {
    $checklist = $this->checklist(TRUE);
    $source = $checklist->getItem('source');
    $handler = $source->getHandler();
    $entity = $handler->doCreateEntity();
    $entity->setName('Interactive record');
    $plugin_form = $this->container->get('plugin_form.factory')->createInstance($handler, 'action');
    $form = ['entity' => ['#entity' => $entity]];
    $plugin_form->submitConfigurationForm($form, new FormState());
    $this->assertSame('interactive', $source->completion_method->value);
    $this->assertTrue($source->isComplete());
    $this->assertEquals($entity->id(), $source->outcomes->get('entity_test')->getValue()->id());
    $this->assertTrue($checklist->process());
    $this->assertSame([['record', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertCount(1, EntityTest::loadMultiple());
  }

  /**
   * A user-selected bundle produces a typed outcome for downstream processing.
   */
  public function testSelectedBundle(): void {
    $checklist = $this->checklist(TRUE, '__select');
    $handler = $checklist->getItem('source')->getHandler();
    $entity = $handler->doCreateEntity('record');
    $handler->completeCreation($entity, 'interactive');
    $this->assertTrue($checklist->process());
    $this->assertSame([['record', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
  }

  /**
   * Missing user selections fail before creating an entity.
   */
  public function testMissingBundleSelection(): void {
    $handler = $this->checklist(TRUE, '__select')->getItem('source')->getHandler();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Select a bundle');
    $handler->doCreateEntity();
  }

  /**
   * A mismatched entity cannot be saved or recorded as a valid outcome.
   */
  public function testMismatchedEntity(): void {
    $source = $this->checklist()->getItem('source');
    $entity = EntityTest::create(['type' => 'entity_test']);
    try {
      $source->getHandler()->completeCreation($entity, 'interactive');
      $this->fail('An entity from the wrong bundle must be rejected.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertStringContainsString('configured entity type and bundle', $exception->getMessage());
    }
    $this->assertTrue($entity->isNew());
    $this->assertTrue($source->isIncomplete());
    $this->assertTrue($source->outcomes->isEmpty());
    $this->assertCount(0, EntityTest::loadMultiple());
  }

}
