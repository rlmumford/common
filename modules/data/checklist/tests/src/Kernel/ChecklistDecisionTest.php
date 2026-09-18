<?php

namespace Drupal\Tests\checklist\Kernel;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Form\ChecklistItemActionForm;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests decisions through forms and structured operations on real checklists.
 *
 * @group checklist
 */
class ChecklistDecisionTest extends KernelTestBase {

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
   * Creates a decision followed by a consumer of its chosen outcome.
   */
  protected function checklist(array $configuration = []): ChecklistInterface {
    $owner = User::create([
      'name' => $this->randomMachineName(),
      'work' => [
        'id' => 'context_test',
        'configuration' => [
          'default_items' => [
            'decision' => [
              'title' => 'Review',
              'handler' => 'decision',
              'handler_configuration' => $configuration + [
                'question' => 'Approve this work?',
                'options' => [
                  'approve' => ['label' => 'Approve', 'require_reason' => TRUE],
                  'decline' => ['label' => 'Decline'],
                  'hidden' => ['label' => 'Hidden', 'available' => ['id' => 'condition_constant:false']],
                ],
              ],
            ],
            'consumer' => [
              'title' => 'Consume decision',
              'handler' => 'context_consumer',
              'handler_configuration' => [
                'context_mapping' => ['value' => 'item:decision:decision'],
                'conditions' => [
                  'actionability' => [
                    'id' => 'condition_string',
                    'condition_string' => "items.decision.status == 'complete'",
                  ],
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
   * Discovery, persisted outcomes and later condition/context use agree.
   */
  public function testOperationOutcomes(): void {
    $checklist = $this->checklist();
    $item = $checklist->getItem('decision');
    $handler = $item->getHandler();
    $this->assertConfigSchema($this->container->get('config.typed'), 'checklist_item_handler.decision', $handler->getConfiguration());
    $contexts = $this->container->get('checklist.context_collector')->collectConfigContexts($checklist);
    $this->assertSame('string', $contexts['item:decision:decision']->getContextDefinition()->getDataType());
    $this->assertFalse($contexts['item:decision:decision']->hasContextValue());
    $this->assertSame(['approve' => 'Approve', 'decline' => 'Decline', 'hidden' => 'Hidden'], $contexts['item:decision:decision']->getContextData()->getPossibleOptions());
    $this->assertSame(['approve', 'decline'], $handler->actionOperations()['choose']['parameters_schema']['properties']['choice']['enum']);
    $this->assertFalse($checklist->process());
    $this->assertFalse($item->isComplete());
    $result = $handler->executeActionOperation('choose', ['choice' => 'approve', 'reason' => ' Reviewed ']);
    $this->assertSame(['decision' => 'approve', 'reason' => 'Reviewed'], $result);
    $this->assertTrue($item->isComplete());
    $this->assertSame([], $handler->actionOperations());

    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $id = $checklist->getEntity()->id();
    $storage->resetCache([$id]);
    $this->container->get('entity_type.manager')->getStorage('checklist_item')->resetCache();
    $reloaded = $storage->load($id)->work->checklist;
    $outcomes = $reloaded->getItem('decision')->get('outcomes');
    $this->assertSame('approve', $outcomes->get('decision')->getValue());
    $this->assertSame('Approve', $outcomes->get('decision')->getValueLabel());
    $this->assertSame(['approve', 'decline', 'hidden'], $outcomes->get('decision')->getPossibleValues());
    $this->assertCount(0, $outcomes->get('decision')->validate());
    $this->assertSame('Reviewed', $outcomes->get('reason')->getValue());
    $this->assertTrue($reloaded->process());
    $this->assertSame([['approve', NULL]], $this->container->get('state')->get('checklist_context_test.runs'));
  }

  /**
   * Persisted item configuration supplies definitions after defaults change.
   */
  public function testPersistedConfigurationOwnsOutcomeDefinition(): void {
    $checklist = $this->checklist();
    $item = $checklist->getItem('decision');
    $item->getHandler()->choose('approve', 'Reviewed');
    $owner = $checklist->getEntity();
    $configuration = $owner->work->configuration;
    $configuration['default_items']['decision']['handler_configuration']['options'] = [
      'decline' => ['label' => 'New default label'],
    ];
    $owner->work->plugin->setConfiguration($configuration);
    $owner->save();

    $manager = $this->container->get('entity_type.manager');
    $manager->getStorage('checklist_item')->resetCache();
    $owner = $manager->getStorage('user')->loadUnchanged($owner->id());
    $this->assertSame(['decline' => ['label' => 'New default label']], $owner->work->configuration['default_items']['decision']['handler_configuration']['options']);
    $item = $owner->work->checklist->getItem('decision');
    $decision = $item->get('outcomes')->get('decision');
    $this->assertSame('approve', $decision->getValue());
    $this->assertSame('Approve', $decision->getValueLabel());
    $this->assertSame('Approve', $item->getHandler()->getConfiguration()['options']['approve']['label']);
    $this->assertCount(0, $decision->validate());
    $this->assertSame('approve', $item->get('outcomes')->first()->value);
    $this->assertNull($item->get('outcomes')->first()->blob);
  }

  /**
   * Forms use the same save path as the choose operation.
   *
   * @dataProvider presentations
   */
  public function testFormChoice(string $presentation): void {
    $item = $this->checklist(['presentation' => $presentation])->getItem('decision');
    $plugin_form = $this->container->get('plugin_form.factory')->createInstance($item->getHandler(), 'action');
    $state = new FormState();
    $wrapper = ChecklistItemActionForm::create($this->container);
    $wrapper->setChecklistItem($item);
    $form = $wrapper->buildForm([], $state);
    if ($presentation === 'buttons') {
      $this->assertArrayNotHasKey('complete', $form['actions']);
      $this->assertArrayNotHasKey('choose_hidden', $form['actions']);
      $button = $form['actions']['choose_decline'];
      $this->assertSame('Decline', $button['#value']);
      $this->assertSame('decline', $button['#decision_choice']);
      $this->assertSame('::onCompleteAjaxCallback', $button['#ajax']['callback']);
      $this->assertSame(['::submitForm'], $button['#submit']);
      $state->setTriggeringElement($button);
      // Posted choice input cannot override the button's machine name.
      $state->setValues(['choice' => 'approve', 'reason' => '']);
    }
    else {
      $this->assertSame('submit', $form['actions']['complete']['#type']);
      $this->assertSame('::onCompleteAjaxCallback', $form['actions']['complete']['#ajax']['callback']);
      $this->assertSame($presentation, $form['choice']['#type']);
      $this->assertSame(['approve' => 'Approve', 'decline' => 'Decline'], $form['choice']['#options']);
      $state->setValues(['choice' => 'decline', 'reason' => '']);
    }
    $plugin_form->validateConfigurationForm($form, $state);
    $this->assertFalse($state->hasAnyErrors());
    $plugin_form->submitConfigurationForm($form, $state);
    $this->assertTrue($item->isComplete());
    $this->assertSame('decline', $item->get('outcomes')->get('decision')->getValue());
  }

  /**
   * Provides supported form presentations.
   */
  public static function presentations(): array {
    return [['buttons'], ['radios'], ['select']];
  }

  /**
   * Missing reasons are reported by form validation without saving anything.
   */
  public function testFormRequiresReason(): void {
    $item = $this->checklist()->getItem('decision');
    $plugin_form = $this->container->get('plugin_form.factory')->createInstance($item->getHandler(), 'action');
    $state = (new FormState())->setValues(['choice' => 'approve', 'reason' => '']);
    $form = $plugin_form->buildConfigurationForm([], $state);
    $state->setTriggeringElement($form['actions']['choose_approve']);
    $plugin_form->validateConfigurationForm($form, $state);
    $this->assertArrayHasKey('choice', $state->getErrors());
    $this->assertStringContainsString('reason', $state->getErrors()['choice']);
    $this->assertTrue($item->isIncomplete());
    $this->assertTrue($item->isNew());
    $state->clearErrors();
  }

  /**
   * Rejected inputs, unavailable choices and missing reasons leave work intact.
   */
  public function testRejectedOperations(): void {
    $item = $this->checklist()->getItem('decision');
    $handler = $item->getHandler();
    foreach ([
      ['unknown', ['choice' => 'decline']],
      ['choose', ['choice' => 'unknown']],
      ['choose', ['choice' => 'hidden']],
      ['choose', ['choice' => 'approve', 'reason' => '  ']],
      ['choose', ['choice' => ['decline']]],
      ['choose', ['choice' => 'decline', 'extra' => TRUE]],
      ['choose', ['choice' => 'decline', 'reason' => NULL]],
    ] as [$operation, $parameters]) {
      $before = $item->get('outcomes')->toArray();
      try {
        $handler->executeActionOperation($operation, $parameters);
        $this->fail('Invalid decisions must be rejected.');
      }
      catch (\InvalidArgumentException) {
        $this->assertSame($before, $item->get('outcomes')->toArray());
        $this->assertTrue($item->isIncomplete());
        $this->assertTrue($item->isNew());
      }
    }
  }

  /**
   * Availability is rechecked after a form has been built.
   */
  public function testChangedAvailability(): void {
    $checklist = $this->checklist([
      'options' => [
        'approve' => [
          'label' => 'Approve',
          'available' => ['id' => 'condition_string', 'condition_string' => "checklist.name.value == 'Eligible'"],
        ],
      ],
    ]);
    $checklist->getEntity()->set('name', 'Eligible');
    $item = $checklist->getItem('decision');
    $plugin_form = $this->container->get('plugin_form.factory')->createInstance($item->getHandler(), 'action');
    $state = (new FormState())->setValues(['choice' => 'approve']);
    $form = $plugin_form->buildConfigurationForm([], $state);
    $this->assertArrayHasKey('choose_approve', $form['actions']);
    $state->setTriggeringElement($form['actions']['choose_approve']);
    $checklist->getEntity()->set('name', 'Ineligible');
    try {
      $plugin_form->submitConfigurationForm($form, $state);
      $this->fail('A stale choice must not submit.');
    }
    catch (\InvalidArgumentException) {
      $this->assertTrue($item->isIncomplete());
      $this->assertTrue($item->isNew());
      $this->assertSame([], $item->getHandler()->actionOperations());
    }
  }

  /**
   * Item gates block operations even when an option has no availability gate.
   */
  public function testItemGate(): void {
    $item = $this->checklist(['conditions' => ['actionability' => ['id' => 'condition_constant:false']]])->getItem('decision');
    $this->assertSame([], $item->getHandler()->actionOperations());
    $this->expectException(\DomainException::class);
    $item->getHandler()->executeActionOperation('choose', ['choice' => 'decline']);
  }

  /**
   * The containing entity's update access applies to operation execution.
   */
  public function testDeniedAccess(): void {
    $item = $this->checklist()->getItem('decision');
    $other = User::create(['name' => 'Other']);
    $other->save();
    $this->container->get('current_user')->setAccount($other);
    $this->assertSame([], $item->getHandler()->actionOperations());
    try {
      $item->getHandler()->executeActionOperation('choose', ['choice' => 'decline']);
      $this->fail('Access must be checked before applying a decision.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue($item->isIncomplete());
      $this->assertTrue($item->isNew());
    }
  }

  /**
   * A completed choice cannot be silently replaced by another submission.
   */
  public function testCompletedDecision(): void {
    $item = $this->checklist()->getItem('decision');
    $item->getHandler()->choose('decline');
    try {
      $item->getHandler()->choose('approve', 'Overwrite');
      $this->fail('Completed decisions cannot be replaced.');
    }
    catch (\DomainException) {
      $this->assertSame('decline', $item->get('outcomes')->get('decision')->getValue());
    }
  }

}
