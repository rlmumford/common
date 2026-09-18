<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Form\ChecklistItemRowForm;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\typed_data_plus\Condition\ConditionException;
use Drupal\user\Entity\User;

/**
 * Tests native condition gates on real checklist fields.
 *
 * @group checklist
 */
class ChecklistConditionTest extends KernelTestBase {

  use SchemaCheckTestTrait;

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
   * Creates a checklist field whose references retain the current owner.
   */
  protected function checklist(array $conditions = [], string $handler = 'simply_checkable'): ChecklistInterface {
    $owner = User::create([
      'name' => $this->randomMachineName(),
      'mail' => 'owner@example.com',
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'source' => ['title' => 'Source', 'handler' => 'context_producer', 'handler_configuration' => []],
            'target' => [
              'title' => 'Target',
              'handler' => $handler,
              'handler_configuration' => [
                'conditions' => $conditions,
                'context_mapping' => $handler === 'context_consumer' ? ['value' => 'item:source:value'] : [],
              ],
            ],
          ],
        ],
      ],
    ]);
    $owner->save();
    return $owner->work->checklist;
  }

  /**
   * A condition string consumes outcomes produced earlier in the same pass.
   */
  public function testOutcomeDependencies(): void {
    $checklist = $this->checklist([
      'actionability' => [
        'id' => 'condition_and',
        'conditions' => [
          ['id' => 'condition_constant:true'],
          [
            'id' => 'condition_string',
            'condition_string' => "items.source.status == 'complete' AND items.source.outcomes.value|upper == 'PRODUCED'",
          ],
        ],
      ],
    ], 'context_consumer');
    $this->assertConfigSchema($this->container->get('config.typed'), 'checklist_item_handler.simply_checkable', ['conditions' => $checklist->getItem('target')->getHandler()->getConfiguration()['conditions']]);
    $this->assertFalse($checklist->getItem('target')->isActionable());
    $this->assertTrue($checklist->process());
    $this->assertSame([['Produced', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
    $this->assertTrue($checklist->getItem('target')->isComplete());
  }

  /**
   * Constant and negated gates control applicability and optional manual work.
   */
  public function testApplicabilityAndRequiredness(): void {
    $checklist = $this->checklist(['applicability' => ['id' => 'condition_constant:true', 'negate' => TRUE]]);
    $this->assertFalse($checklist->getItem('target')->isApplicable());
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->getItem('target')->isIncomplete());

    $checklist = $this->checklist(['required' => ['id' => 'condition_constant:false']]);
    $this->assertFalse($checklist->getItem('target')->isRequired());
    $this->assertTrue($checklist->process());
    $this->assertTrue($checklist->getItem('target')->isIncomplete());
  }

  /**
   * Native conditions receive mapped contexts and re-evaluate fresh values.
   */
  public function testNativeConditionAndFreshness(): void {
    $checklist = $this->checklist([
      'applicability' => [
        'id' => 'user_role',
        'roles' => ['authenticated'],
        'context_mapping' => ['user' => 'checklist:entity'],
      ],
      'actionability' => ['id' => 'condition_string', 'condition_string' => "items.source.outcomes.value == 'Ready'"],
    ]);
    $target = $checklist->getItem('target');
    $this->assertTrue($target->isApplicable());
    $source = $checklist->getItem('source');
    $this->assertFalse($target->isActionable());
    $source->setOutcome('value', 'Ready');
    $this->assertTrue($target->isActionable());
    $source->setOutcome('value', NULL);
    $this->assertFalse($target->isActionable());
  }

  /**
   * Missing required contexts stay unknown, never optional by accident.
   */
  public function testMissingRequiredContext(): void {
    $condition = [
      'id' => 'user_role',
      'roles' => ['authenticated'],
      'context_mapping' => ['user' => '@user.current_user_context:current_user'],
    ];
    $checklist = $this->checklist(['applicability' => $condition]);
    $this->assertNull($checklist->getItem('target')->isApplicable());
    $this->assertFalse($checklist->process());
    $this->assertFalse($checklist->isCompletable());

    $checklist = $this->checklist(['required' => $condition]);
    $this->assertTrue($checklist->getItem('target')->isRequired());
  }

  /**
   * Direct item action cannot bypass a configured actionability gate.
   */
  public function testDirectActionBlocked(): void {
    $item = $this->checklist(['actionability' => ['id' => 'condition_constant:false']])->getItem('target');
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('not actionable');
    $item->action();
  }

  /**
   * Form submissions recheck gates before invoking the plugin submit method.
   */
  public function testFormSubmissionBlocked(): void {
    $checklist = $this->checklist(['actionability' => ['id' => 'condition_constant:false']]);
    $this->container->get('current_user')->setAccount($checklist->getEntity());
    $item = $checklist->getItem('target');
    $this->assertInstanceOf(ChecklistItemActionForm::class, ChecklistItemActionForm::create($this->container));
    $form_object = ChecklistItemRowForm::create($this->container);
    $form_object->setChecklistItem($item);
    $form = [];
    try {
      $form_object->submitForm($form, new FormState());
      $this->fail('A blocked item must not submit.');
    }
    catch (\LogicException $exception) {
      $this->assertStringContainsString('not actionable', $exception->getMessage());
    }
    $this->assertTrue($item->isIncomplete());
    $this->assertTrue($item->isNew());
  }

  /**
   * Custom entity handler factories inject the shared evaluator as well.
   */
  public function testEntityHandlerFactories(): void {
    $item = $this->checklist()->getItem('target');
    $manager = $this->container->get('plugin.manager.checklist_item_handler');
    foreach (['create_entity:user', 'update_entity:user'] as $id) {
      $handler = $manager->createInstance($id, [
        'conditions' => ['required' => ['id' => 'condition_constant:false']],
      ]);
      $handler->setItem($item);
      $this->assertFalse($handler->isRequired());
    }
  }

  /**
   * Invalid syntax remains a configuration error rather than skipped work.
   */
  public function testInvalidCondition(): void {
    $checklist = $this->checklist([
      'applicability' => [
        'id' => 'condition_string',
        'condition_string' => 'unsupported syntax',
      ],
    ]);
    $this->expectException(ConditionException::class);
    $checklist->process();
  }

}
