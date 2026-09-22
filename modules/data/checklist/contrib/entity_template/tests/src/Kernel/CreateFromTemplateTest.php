<?php

namespace Drupal\Tests\checklist_entity_template\Kernel;

use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;
use Drupal\flexiform\Api\InvalidInputException;
use Drupal\flexiform\Entity\FormDefinition;
use Drupal\Core\Form\FormState;
use Drupal\flexiform\Session\HtmlFormAdapter;
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
    $this->assertFalse($item->getHandler()->hasFormClass('action'));
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

  /**
   * Builds a transient entity editor, optionally with two wizard pages.
   */
  protected function editorConfiguration(bool $wizard = FALSE): array {
    $configuration = [
      'data' => ['entity' => ['plugin' => 'provided_data']],
      'components' => [
        'name' => ['component_type' => 'typed_data', 'context' => 'entity', 'path' => 'name.0.value', 'label' => 'Name'],
      ],
    ];
    if ($wizard) {
      $configuration['components']['language'] = [
        'component_type' => 'typed_data',
        'context' => 'entity',
        'path' => 'langcode.0.value',
        'label' => 'Language',
      ];
      $configuration['pages'] = [
        ['label' => 'Name', 'components' => ['name']],
        ['label' => 'Language', 'components' => ['language']],
      ];
    }
    return ['editor' => ['plugin' => $wizard ? 'wizard' : 'standard', 'configuration' => $configuration]];
  }

  /**
   * HTML and API share revisions, values and a single final entity save.
   */
  public function testSharedEditor(): void {
    [$host, $item] = $this->templateWork(settings: $this->editorConfiguration());
    $editor = $this->container->get('checklist_entity_template.editor');
    $this->assertSame('new', $editor->describe($item)['status']);
    $this->container->get('checklist.processor')->process($host->work->checklist);
    $this->assertNull($this->container->get('checklist.attempt_journal')->latest($item));
    $dispatcher = $this->container->get('checklist.action_operation_dispatcher');
    $this->assertArrayHasKey('start', $dispatcher->discover($host->work->checklist, 'create'));
    $description = $dispatcher->execute($host->work->checklist, 'create', 'start', ['revision' => 0]);
    $this->assertSame('ready', $description['status']);
    $this->assertSame('Template target', $description['data']->name);
    $this->assertCount(1, EntityTest::loadMultiple());
    $html = new HtmlFormAdapter();
    $form = $html->build($description, ['editor']);
    $tempstore = $this->container->get('checklist.tempstore_repository');
    $tempstore->set($host->work->checklist);
    $old_revision = $description['revision'];
    $description = $editor->operate($item, 'form/update', [
      'revision' => $old_revision,
      'input' => ['name' => 'API edit'],
    ]);
    $this->assertSame('API edit', $html->build($description, ['editor'])['values']['name']['#default_value']);
    try {
      $editor->operate($item, 'form/submit', ['revision' => $old_revision, 'input' => ['name' => 'Stale browser']]);
      $this->fail('A stale browser must not overwrite API edits.');
    }
    catch (ChecklistAttemptConflictException) {
      $this->assertCount(1, EntityTest::loadMultiple());
    }
    $form = $html->build($description, ['editor']);
    $state = new FormState();
    $button = $form['actions']['action_0'];
    $state->setTriggeringElement($button);
    $state->setValue(['editor', 'values', 'name'], 'HTML edit');
    $description = $editor->operate($item, 'form/' . $button['#flexiform_action'], [
      'revision' => $description['revision'],
      'input' => $html->input($state),
    ]);
    $this->assertSame('HTML edit', $description['data']->name);
    $done = $editor->operate($item, 'form/submit', ['revision' => $description['revision'], 'input' => []]);
    $this->assertSame('complete', $done['status']);
    $this->assertSame('HTML edit', $this->reload($item)->get('outcomes')->get('entity')->getValue()->label());
    $cached = $tempstore->get($host->work->checklist)->getItem('create');
    $this->assertTrue($cached->isComplete());
    $this->assertSame('HTML edit', $cached->get('outcomes')->get('entity')->getValue()->label());
    $this->assertTrue($this->reload($item)->get('state')->isEmpty());
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * Preparation and wizard pages retain the same entity until Finish.
   */
  public function testSharedWizard(): void {
    [, $item] = $this->templateWork('checklist_pending', settings: $this->editorConfiguration(TRUE));
    $editor = $this->container->get('checklist_entity_template.editor');
    $description = $editor->operate($item, 'start', ['revision' => 0]);
    $this->assertSame('preparing', $description['status']);
    $this->assertArrayNotHasKey('data', $description);
    $description = $editor->operate($item, 'advance', ['revision' => $description['revision']]);
    $this->assertSame('ready', $description['status']);
    $description = $editor->operate($item, 'form/next', [
      'revision' => $description['revision'],
      'input' => ['name' => 'Wizard edit'],
    ]);
    $this->assertSame(1, $description['wizard']['page']);
    $this->assertCount(1, EntityTest::loadMultiple());
    $description = $editor->operate($item, 'form/previous', ['revision' => $description['revision'], 'input' => []]);
    $this->assertSame('Wizard edit', $description['data']->name);
    $description = $editor->operate($item, 'form/next', ['revision' => $description['revision'], 'input' => []]);
    $description = $editor->operate($item, 'form/finish', ['revision' => $description['revision'], 'input' => []]);
    $this->assertSame('complete', $description['status']);
    $this->assertCount(2, EntityTest::loadMultiple());
  }

  /**
   * Other users cannot read or mutate the owner's prepared entity.
   */
  public function testSharedEditorOwner(): void {
    [, $item] = $this->templateWork(settings: $this->editorConfiguration());
    $editor = $this->container->get('checklist_entity_template.editor');
    $editor->operate($item, 'start', ['revision' => 0]);
    Role::create(['id' => 'editor_admin', 'label' => 'Editor administrator'])->setIsAdmin(TRUE)->save();
    $other = User::create(['name' => 'Other', 'status' => 1, 'roles' => ['editor_admin']]);
    $other->save();
    $this->container->get('current_user')->setAccount($other);
    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('belongs to another');
    $editor->describe($item);
  }

  /**
   * Invalid edits leave state unchanged; revoked create access prevents Finish.
   */
  public function testEditorValidationAndRevokedAccess(): void {
    [, $item] = $this->templateWork(settings: $this->editorConfiguration());
    $editor = $this->container->get('checklist_entity_template.editor');
    $ready = $editor->operate($item, 'start', ['revision' => 0]);
    try {
      $editor->operate($item, 'form/update', ['revision' => $ready['revision'], 'input' => ['unknown' => 'value']]);
      $this->fail('Unknown input must be rejected.');
    }
    catch (InvalidInputException) {
      $this->assertSame($ready['revision'], $editor->describe($item)['revision']);
      $this->assertSame('Template target', $editor->describe($item)['data']->name);
    }
    $this->container->get('state')->set('checklist_template_test.deny_create', TRUE);
    $this->container->get('entity_type.manager')->getAccessControlHandler('entity_test')->resetCache();
    try {
      $editor->operate($item, 'form/submit', ['revision' => $ready['revision'], 'input' => []]);
      $this->fail('Revoked permission must prevent saving.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertCount(1, EntityTest::loadMultiple());
      $this->assertTrue($this->reload($item)->get('outcomes')->isEmpty());
      $this->assertFalse($this->reload($item)->get('state')->isEmpty());
    }
  }

  /**
   * A final entity-save failure retains the edited graph and attempt history.
   */
  public function testEditorSaveFailure(): void {
    [, $item] = $this->templateWork(settings: $this->editorConfiguration());
    $editor = $this->container->get('checklist_entity_template.editor');
    $ready = $editor->operate($item, 'start', ['revision' => 0]);
    $ready = $editor->operate($item, 'form/update', [
      'revision' => $ready['revision'],
      'input' => ['name' => 'Retain my edit'],
    ]);
    $this->container->get('state')->set('checklist_template_test.fail_save', TRUE);
    try {
      $editor->operate($item, 'form/submit', ['revision' => $ready['revision'], 'input' => []]);
      $this->fail('Failed persistence must not complete the item.');
    }
    catch (EntityStorageException) {
      $this->assertSame('failed', $editor->describe($item)['status']);
      $saved = $this->reload($item);
      $this->assertTrue($saved->get('outcomes')->isEmpty());
      $this->assertFalse($saved->isComplete());
      [$session] = $saved->getHandler()->getEditorSession();
      $this->assertSame('Retain my edit', $session->describe()['data']->name);
      $this->container->get('entity_type.manager')->getStorage('entity_test')->resetCache();
      $this->assertCount(1, EntityTest::loadMultiple());
    }
  }

  /**
   * Referenced editors are captured and do not change underneath open forms.
   */
  public function testReusableEditorSnapshot(): void {
    $configuration = $this->editorConfiguration()['editor']['configuration'];
    $definition = FormDefinition::create([
      'id' => 'review',
      'label' => 'Review',
      'plugin' => 'standard',
      'configuration' => $configuration,
    ]);
    $definition->save();
    [, $item] = $this->templateWork(settings: ['editor' => ['form_id' => 'review']]);
    $this->assertContains('flexiform.form.review', $item->getHandler()->calculateDependencies()['config']);
    $editor = $this->container->get('checklist_entity_template.editor');
    $ready = $editor->operate($item, 'start', ['revision' => 0]);
    $configuration['components'] = [];
    $definition->set('configuration', $configuration)->save();
    $editor->operate($item, 'form/submit', [
      'revision' => $ready['revision'],
      'input' => ['name' => 'Captured editor'],
    ]);
    $this->assertSame('Captured editor', $this->reload($item)->get('outcomes')->get('entity')->getValue()->label());
  }

}
