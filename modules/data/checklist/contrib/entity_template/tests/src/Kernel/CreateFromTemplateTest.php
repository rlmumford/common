<?php

namespace Drupal\Tests\checklist_entity_template\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist_template_test\Plugin\EntityTemplate\Component\Pending;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_template\Entity\TemplateBlueprint;
use Drupal\entity_template\Entity\TemplateBuilder;
use Drupal\Tests\checklist\Kernel\ChecklistItemExecutionTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests real template execution through persisted checklist attempts.
 *
 * @group checklist_entity_template
 */
class CreateFromTemplateTest extends ChecklistItemExecutionTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'entity_template', 'checklist_entity_template', 'checklist_template_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    FieldStorageConfig::create(['field_name' => 'work', 'entity_type' => 'entity_test', 'type' => 'checklist'])->save();
    FieldConfig::create([
      'field_name' => 'work',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'translatable' => FALSE,
    ])->save();
    Pending::$during = NULL;
  }

  /**
   * Creates persisted work with a template and a downstream context consumer.
   */
  protected function templateWork(string $component = 'property_context', string $value = '', array $settings = []): array {
    $host = EntityTest::create([
      'name' => 'Template target',
      'work' => [
        'id' => 'template_test',
        'configuration' => [
          'default_items' => [
            'create' => [
              'title' => 'Create from template',
              'handler' => 'entity_template__create',
              'handler_configuration' => [
                'template' => [
                  'id' => 'standalone',
                  'target_entity_type_id' => 'entity_test',
                  'target_entity_bundle' => 'entity_test',
                  'parameters' => ['title' => ['type' => 'string', 'required' => TRUE]],
                  'components' => [
                    'title' => [
                      'id' => $component,
                      'path' => 'name.0.value',
                      'context_mapping' => ['value' => 'title'],
                    ],
                  ],
                ],
                'context_mapping' => ['title' => 'checklist:entity.name.value'],
              ],
            ],
            'consumer' => [
              'title' => 'Read the created entity',
              'handler' => 'context_consumer',
              'handler_configuration' => ['context_mapping' => ['value' => 'item:create:entity.name.value']],
            ],
          ],
        ],
      ],
    ]);
    if ($component === 'checklist_pending') {
      $configuration = $host->work->configuration;
      $configuration['default_items']['create']['handler_configuration']['template']['components']['title']['value'] = $value;
      $host->work->configuration = $configuration;
    }
    if ($settings) {
      $configuration = $host->work->configuration;
      $configuration['default_items']['create']['handler_configuration'] = $settings + $configuration['default_items']['create']['handler_configuration'];
      $host->work->configuration = $configuration;
    }
    $host->save();
    foreach ($host->work->checklist->getItems() as $item) {
      $item->save();
      if ($item->getName() === 'create') {
        $this->assertConfigSchema($this->container->get('config.typed'), 'checklist_item_handler.entity_template__create', $item->getHandler()->getConfiguration());
      }
    }
    return [$host, $host->work->checklist->getItem('create')];
  }

  /**
   * Inline creation publishes a saved entity usable by the next item.
   */
  public function testInlineCreationAndOutcome(): void {
    [$host, $item] = $this->templateWork();
    $this->assertContains('entity_template', $item->getHandler()->calculateDependencies()['module']);
    $this->assertTrue($this->container->get('checklist.processor')->process($host->work->checklist));
    $saved = $this->reload($item);
    $this->assertTrue($saved->isComplete());
    $this->assertTrue($saved->get('state')->isEmpty());
    $entity = $saved->get('outcomes')->get('entity')->getValue();
    $this->assertFalse($entity->isNew());
    $this->assertSame('Template target', $entity->label());
    $this->assertSame([['Template target', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertCount(2, EntityTest::loadMultiple());
    $attempt = $this->container->get('checklist.item_executor')->submit($item);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $attempt->status);
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * Polling resumes captured state and creates nothing before completion.
   */
  public function testContinuation(): void {
    [, $item] = $this->templateWork('checklist_pending');
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->assertSame(ChecklistAttempt::WAITING, $waiting->status);
    $this->assertCount(1, EntityTest::loadMultiple());
    $saved = $this->reload($item);
    $this->assertFalse($saved->get('state')->isEmpty());
    $this->assertTrue($saved->get('outcomes')->isEmpty());
    $this->assertSame('preparing', $saved->getHandler()->getActionState()->stage);
    $this->now += 1;
    $finished = $runner->run($waiting);
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $finished->status);
    $this->assertSame($waiting->id, $finished->id);
    $this->assertTrue($this->reload($item)->get('state')->isEmpty());
    $this->assertCount(2, EntityTest::loadMultiple());
    $this->expectException(ChecklistAttemptConflictException::class);
    $runner->run($waiting);
  }

  /**
   * Failures retain private preparation without publishing diagnostics.
   */
  public function testFailure(): void {
    [, $item] = $this->templateWork('checklist_pending', 'fail');
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->now += 1;
    $failed = $runner->run($waiting);
    $this->assertSame(ChecklistAttempt::FAILED, $failed->status);
    $saved = $this->reload($item);
    $state = $saved->get('state')->get('preparation')->getValue();
    [, $execution] = PhpSerialize::decode($state['snapshot']);
    $this->assertTrue($execution->getState()->failed);
    $this->assertSame('retained-request', $execution->getState()->componentState['run']);
    $this->assertTrue($saved->get('outcomes')->isEmpty());
    $this->assertCount(1, EntityTest::loadMultiple());
    $history = $this->container->get('checklist.attempt_journal')->history($failed->id);
    $this->assertStringNotContainsString('Private provider', json_encode($history));
  }

  /**
   * A stale claim cannot save the target prepared outside the transaction.
   */
  public function testStaleCompletion(): void {
    [, $item] = $this->templateWork('checklist_pending');
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->now += 1;
    Pending::$during = function (): void {
      $this->now += 301;
    };
    try {
      $runner->run($waiting);
      $this->fail('The expired claim must reject creation.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->assertCount(1, EntityTest::loadMultiple());
      $this->assertFalse($this->reload($item)->isComplete());
    }
  }

  /**
   * A revoked create grant is checked again before local entity persistence.
   */
  public function testRevokedCreateAccess(): void {
    [, $item] = $this->templateWork('checklist_pending');
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->now += 1;
    Pending::$during = function (): void {
      $this->container->get('state')->set('checklist_template_test.deny_create', TRUE);
    };
    try {
      $runner->run($waiting);
      $this->fail('Revoked access must prevent creation.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertCount(1, EntityTest::loadMultiple());
      $this->assertTrue($this->reload($item)->get('outcomes')->isEmpty());
      $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->latest($item)->status);
    }
  }

  /**
   * Save failures roll back the entity and outcome, retaining history.
   */
  public function testSaveFailure(): void {
    [, $item] = $this->templateWork('checklist_pending');
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->now += 1;
    $this->container->get('state')->set('checklist_template_test.fail_save', TRUE);
    try {
      $runner->run($waiting);
      $this->fail('The save failure must propagate.');
    }
    catch (EntityStorageException) {
      $this->container->get('entity_type.manager')->getStorage('entity_test')->resetCache();
      $this->assertCount(1, EntityTest::loadMultiple());
      $saved = $this->reload($item);
      $this->assertTrue($saved->get('outcomes')->isEmpty());
      $this->assertFalse($saved->get('state')->isEmpty());
      $this->assertFalse($saved->isComplete());
      $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->latest($item)->status);
    }
  }

  /**
   * Referenced templates capture configuration before a suspended execution.
   */
  public function testBlueprintSnapshot(): void {
    TemplateBuilder::create([
      'id' => 'example',
      'label' => 'Example',
      'return_type' => 'entity:entity_test:entity_test',
      'parameters' => [['machine_name' => 'title', 'type' => 'string', 'label' => 'Title']],
    ])->save();
    $this->container->get('plugin.manager.entity_template.builder')->clearCachedDefinitions();
    $blueprint = TemplateBlueprint::create([
      'id' => 'example',
      'label' => 'Example',
      'builder' => 'config:example',
      'templates' => [
        'main' => [
          'id' => 'default',
          'components' => ['title' => ['id' => 'checklist_pending', 'path' => 'name.0.value', 'value' => '']],
        ],
      ],
    ]);
    $blueprint->save();
    [, $item] = $this->templateWork(settings: ['blueprint' => 'example', 'template_id' => 'main', 'template' => []]);
    $dependencies = $item->getHandler()->calculateDependencies();
    $this->assertContains('entity_template.blueprint.example', $dependencies['config']);
    $this->assertContains('entity_template.builder.example', $dependencies['config']);
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $templates = $blueprint->getTemplates();
    $templates['main']['components']['title']['value'] = 'fail';
    $blueprint->set('templates', $templates)->save();
    $this->now += 1;
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $runner->run($waiting)->status);
    $this->assertSame('Template target', $this->reload($item)->get('outcomes')->get('entity')->getValue()->label());
  }

  /**
   * Losing access to an earlier field edit prevents resumed persistence.
   */
  public function testRevokedFieldAccess(): void {
    [, $item] = $this->templateWork('checklist_pending');
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->container->get('state')->set('checklist_resolver_test.denied_field_operations', ['name' => ['edit']]);
    $this->now += 1;
    $this->assertSame(ChecklistAttempt::FAILED, $runner->run($waiting)->status);
    $saved = $this->reload($item);
    $this->assertFalse($saved->get('state')->isEmpty());
    $this->assertTrue($saved->get('outcomes')->isEmpty());
    $this->assertCount(1, EntityTest::loadMultiple());
  }

}
