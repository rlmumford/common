<?php

namespace Drupal\Tests\checklist_entity_template\Kernel;

use Drupal\checklist\Attempt\ChecklistAttempt;
use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\checklist_template_test\Plugin\EntityTemplate\Component\Pending;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\checklist\Kernel\ChecklistItemExecutionTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests existing-entity execution, shared editing and retained target identity.
 *
 * @group checklist_entity_template
 */
class ApplyTemplateToEntityTest extends ChecklistItemExecutionTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ctools', 'token', 'flexiform', 'entity_test', 'entity_template',
    'checklist_entity_template', 'checklist_template_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    foreach (['work' => 'checklist', 'target' => 'entity_reference'] as $name => $type) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'entity_test',
        'type' => $type,
        'settings' => $name === 'target' ? ['target_type' => 'entity_test'] : [],
      ])->save();
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'entity_test',
        'bundle' => 'entity_test',
        'translatable' => FALSE,
      ])->save();
    }
    Pending::$during = NULL;
  }

  /**
   * Creates a target and a checklist pointing to it through a related context.
   */
  protected function applyWork(bool $pending = FALSE, bool $editor = FALSE, bool $choice = FALSE): array {
    $target = EntityTest::create(['name' => 'Original']);
    $target->save();
    $candidate = [
      'template' => [
        'type' => 'embedded',
        'configuration' => [
          'id' => 'standalone',
          'label' => 'Update record',
          'target_entity_type_id' => 'entity_test',
          'target_entity_bundle' => 'entity_test',
          'parameters' => ['title' => ['type' => 'string', 'required' => TRUE]],
          'components' => [
            'name' => [
              'id' => $pending ? 'checklist_pending' : 'property_context',
              'path' => 'name.0.value',
              'context_mapping' => ['value' => 'title'],
            ],
          ],
        ],
      ],
      'context_mapping' => ['title' => 'checklist:entity.name.value'],
    ];
    if ($pending) {
      $candidate['template']['configuration']['components']['name']['value'] = '';
    }
    if ($editor) {
      $candidate['editor'] = [
        'plugin' => 'standard',
        'configuration' => [
          'data' => ['entity' => ['plugin' => 'provided_data']],
          'components' => [
            'name' => [
              'component_type' => 'typed_data',
              'context' => 'entity',
              'path' => 'name.0.value',
            ],
          ],
        ],
      ];
    }
    $templates = ['default' => $candidate];
    if ($choice) {
      $templates['alternative'] = $candidate;
      $templates['alternative']['template']['configuration']['label'] = 'Alternative';
      $templates['alternative']['template']['configuration']['components']['name'] = [
        'id' => 'property_value',
        'path' => 'name.0.value',
        'value' => 'Selected',
      ];
    }
    $host = EntityTest::create([
      'name' => 'Applied',
      'target' => $target,
      'work' => [
        'id' => 'template_test',
        'configuration' => [
          'default_items' => [
            'apply' => [
              'title' => 'Apply template',
              'handler' => 'entity_template__apply_to',
              'handler_configuration' => [
                'context_mapping' => ['target' => 'checklist:entity.target.0.entity'],
                'templates' => $templates,
              ],
            ],
            'consumer' => [
              'title' => 'Read updated outcome',
              'handler' => 'context_consumer',
              'handler_configuration' => [
                'context_mapping' => ['value' => 'item:apply:entity.name.value'],
              ],
            ],
          ],
        ],
      ],
    ]);
    $host->save();
    foreach ($host->work->checklist->getItems() as $item) {
      $item->save();
    }
    $item = $host->work->checklist->getItem('apply');
    $this->assertConfigSchema($this->container->get('config.typed'), 'checklist_item_handler.entity_template__apply_to', $item->getHandler()->getConfiguration());
    return [$target, $host, $item];
  }

  /**
   * Updates one existing entity and supplies its outcome to later items.
   */
  public function testInlineAndOutcome(): void {
    [$target, $host, $item] = $this->applyWork();
    $this->assertTrue($this->container->get('checklist.processor')->process($host->work->checklist));
    $saved = $this->reload($item);
    $this->assertTrue($saved->isComplete());
    $outcome = $saved->get('outcomes')->get('entity')->getValue();
    $this->assertSame($target->id(), $outcome->id());
    $this->assertSame('Applied', $outcome->label());
    $this->assertSame('Original', $target->label());
    $this->assertSame([['Applied', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertCount(2, EntityTest::loadMultiple());
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $this->container->get('checklist.item_executor')->submit($item)->status);
    $this->assertTrue($saved->get('state')->isEmpty());
  }

  /**
   * Pending preparation changes no stored values and resumes the same target.
   */
  public function testResumption(): void {
    [$target, , $item] = $this->applyWork(TRUE);
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $this->assertSame(ChecklistAttempt::WAITING, $waiting->status);
    $this->assertSame('Original', EntityTest::load($target->id())->label());
    $this->assertTrue($this->reload($item)->get('outcomes')->isEmpty());
    $this->now++;
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $runner->run($waiting)->status);
    $this->assertSame('Applied', EntityTest::load($target->id())->label());
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * An intervening save fails safely and preserves diagnostics, not an outcome.
   */
  public function testChangedTarget(): void {
    [$target, , $item] = $this->applyWork(TRUE);
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $target->setName('Concurrent edit')->save();
    $this->now++;
    $this->assertSame(ChecklistAttempt::FAILED, $runner->run($waiting)->status);
    $saved = $this->reload($item);
    $this->assertTrue($saved->isFailed());
    $this->assertFalse($saved->get('state')->isEmpty());
    $this->assertTrue($saved->get('outcomes')->isEmpty());
    $this->assertSame('Concurrent edit', EntityTest::load($target->id())->label());
  }

  /**
   * Template choices and API edits keep data private until final submission.
   */
  public function testSharedEditor(): void {
    [$target, $host, $item] = $this->applyWork(editor: TRUE, choice: TRUE);
    $dispatcher = $this->container->get('checklist.action_operation_dispatcher');
    $description = $dispatcher->execute($host->work->checklist, 'apply', 'get', []);
    $this->assertCount(2, $description['templates']);
    $description = $dispatcher->execute($host->work->checklist, 'apply', 'start', [
      'revision' => 0,
      'template' => 'alternative',
    ]);
    $this->assertSame('Selected', $description['data']->name);
    $editor = $this->container->get('checklist_entity_template.editor');
    $description = $editor->operate($item, 'form/update', [
      'revision' => $description['revision'],
      'input' => ['name' => 'Human edit'],
    ]);
    $this->assertSame('Original', EntityTest::load($target->id())->label());
    $done = $editor->operate($item, 'form/submit', ['revision' => $description['revision'], 'input' => []]);
    $this->assertSame('complete', $done['status']);
    $this->assertSame('Human edit', EntityTest::load($target->id())->label());
    $this->assertSame($target->id(), $this->reload($item)->get('outcomes')->get('entity')->getValue()->id());
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * An editor cannot overwrite a target saved by another user while it is open.
   */
  public function testEditorConflict(): void {
    [$target, , $item] = $this->applyWork(editor: TRUE);
    $editor = $this->container->get('checklist_entity_template.editor');
    $description = $editor->operate($item, 'start', ['revision' => 0]);
    $target->setName('Concurrent edit')->save();
    $this->expectException(ChecklistAttemptConflictException::class);
    $editor->operate($item, 'form/submit', ['revision' => $description['revision'], 'input' => ['name' => 'Stale edit']]);
  }

  /**
   * Revoked update access prevents reading or saving the retained editor.
   */
  public function testRevokedAccess(): void {
    [$target, , $item] = $this->applyWork(editor: TRUE);
    $editor = $this->container->get('checklist_entity_template.editor');
    $editor->operate($item, 'start', ['revision' => 0]);
    $this->container->get('state')->set('checklist_template_test.deny_update', $target->id());
    $this->expectException(AccessDeniedHttpException::class);
    $editor->describe($item);
  }

  /**
   * Missing target contexts leave work unstarted and never create an entity.
   */
  public function testMissingTarget(): void {
    [, $host, $item] = $this->applyWork();
    $host->target = [];
    $host->save();
    $this->container->get('checklist.processor')->process($host->work->checklist);
    $this->assertNull($this->container->get('checklist.attempt_journal')->latest($item));
    $this->assertFalse($this->reload($item)->isComplete());
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * A deleted target cannot be recreated by resuming its retained working copy.
   */
  public function testDeletedTarget(): void {
    [$target, , $item] = $this->applyWork(editor: TRUE);
    $editor = $this->container->get('checklist_entity_template.editor');
    $editor->operate($item, 'start', ['revision' => 0]);
    $target->delete();
    $this->expectException(ChecklistAttemptConflictException::class);
    $editor->describe($item);
  }

  /**
   * Remapping the input cannot redirect a retained attempt to another record.
   */
  public function testRemappedTarget(): void {
    [$target, $host, $item] = $this->applyWork(TRUE);
    $runner = $this->container->get('checklist.item_executor');
    $waiting = $runner->submit($item);
    $replacement = EntityTest::create(['name' => 'Replacement']);
    $replacement->save();
    $host->target = $replacement;
    $host->save();
    $this->now++;
    $this->assertSame(ChecklistAttempt::FAILED, $runner->run($waiting)->status);
    $this->assertSame('Original', EntityTest::load($target->id())->label());
    $this->assertSame('Replacement', EntityTest::load($replacement->id())->label());
    $this->assertTrue($this->reload($item)->get('outcomes')->isEmpty());
  }

  /**
   * A failing update rolls back the target and retains the failed editor.
   */
  public function testSaveFailure(): void {
    [$target, , $item] = $this->applyWork(editor: TRUE);
    $editor = $this->container->get('checklist_entity_template.editor');
    $description = $editor->operate($item, 'start', ['revision' => 0]);
    $this->container->get('state')->set('checklist_template_test.fail_update', $target->id());
    try {
      $editor->operate($item, 'form/submit', ['revision' => $description['revision'], 'input' => []]);
      $this->fail('The failing entity update must abort completion.');
    }
    catch (EntityStorageException) {
      $saved = $this->reload($item);
      $this->assertFalse($saved->isComplete());
      $this->assertFalse($saved->get('state')->isEmpty());
      $this->assertTrue($saved->get('outcomes')->isEmpty());
      $this->assertSame(ChecklistAttempt::FAILED, $this->container->get('checklist.attempt_journal')->latest($item)->status);
      $this->assertSame('Original', $this->container->get('entity_type.manager')->getStorage('entity_test')->loadUnchanged($target->id())->label());
    }
  }

  /**
   * Candidate gates and template self conditions use the selected entity.
   */
  public function testConditionalChoice(): void {
    [, , $item] = $this->applyWork(choice: TRUE);
    $configuration = $item->getHandler()->getConfiguration();
    $configuration['templates']['default']['condition'] = ['id' => 'condition_constant:false'];
    $configuration['templates']['alternative']['template']['configuration']['conditions'] = [
      ['id' => 'condition_string', 'condition_string' => 'self.name.0.value == "Original"'],
    ];
    $item->getHandler()->setConfiguration($configuration);
    $item->save();
    $this->assertSame(ChecklistAttempt::SUCCEEDED, $this->container->get('checklist.item_executor')->submit($item)->status);
    $this->assertSame('Selected', $this->reload($item)->get('outcomes')->get('entity')->getValue()->label());
  }

}
