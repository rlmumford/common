<?php

namespace Drupal\Tests\checklist_entity_template\Functional;

use Drupal\task_job\Entity\Job;
use Drupal\task\Entity\Task;
use Drupal\entity_template\Entity\TemplateBuilder;
use Drupal\entity_template\Entity\TemplateBlueprint;
use Drupal\flexiform\Entity\FormDefinition;
use Drupal\Tests\BrowserTestBase;

/**
 * Exercises job configuration through nested plugin forms and draft storage.
 *
 * @group checklist_entity_template
 */
class JobAuthoringTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['task_job', 'checklist_entity_template_ui', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a configuration-only job and logs in its administrator.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    Job::create(['id' => 'authoring', 'label' => 'Authoring'])->save();
  }

  /**
   * Saves decisions and nested conditions while retaining unsaved job changes.
   */
  public function testDecision(): void {
    $this->drupalGet('/admin/config/task/job/authoring/checklist/add/decision');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name' => 'review',
      'label' => 'Review document',
      'plugin_configuration[question]' => 'Approve this document?',
      'plugin_configuration[options][0][name]' => 'approve',
      'plugin_configuration[options][0][label]' => 'Approve',
    ], 'plugin_configuration_options_add');
    $this->assertSession()->fieldValueEquals('plugin_configuration[question]', 'Approve this document?');
    $this->submitForm([
      'plugin_configuration[options][1][name]' => 'reject',
      'plugin_configuration[options][1][label]' => 'Reject',
      'plugin_configuration[options][1][require_reason]' => TRUE,
      'conditions[required][id]' => 'condition_and',
    ], 'conditions_required_update');
    $this->submitForm([
      'conditions[required][children][0][id]' => 'condition_constant:false',
    ], 'conditions_required_children_0_update');
    $this->submitForm([], 'Add');
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->assertCount(0, Job::load('authoring')->getChecklistItems());
    $this->drupalGet('/admin/config/task/job/authoring/checklist/add/entity_template__create');
    $this->assertSession()->pageTextContains('item:review:decision');
    $this->drupalGet('/admin/config/task/job/authoring/edit');
    $this->submitForm([], 'Save');
    $job = $this->container->get('entity_type.manager')->getStorage('task_job')->loadUnchanged('authoring');
    $configuration = $job->getChecklistItems()['review']['handler_configuration'];
    $this->assertSame('Approve', $configuration['options']['approve']['label']);
    $this->assertTrue($configuration['options']['reject']['require_reason']);
    $this->assertSame('buttons', $configuration['presentation']);
    $this->assertSame('condition_constant:false', $configuration['conditions']['required']['conditions'][0]['id']);
    $this->assertContains('typed_data_plus', $job->getDependencies()['module']);
    $this->drupalGet('/admin/config/task/job/authoring/checklist/review/configure');
    $this->assertSession()->fieldValueEquals('plugin_configuration[options][1][name]', 'reject');
    $this->submitForm(['plugin_configuration[question]' => 'Revised question'], 'Update');
    $this->submitForm([], 'Cancel');
    $this->drupalGet('/admin/config/task/job/authoring/checklist/review/configure');
    $this->assertSession()->fieldValueEquals('plugin_configuration[question]', 'Approve this document?');
  }

  /**
   * Builds a template and embedded editor without writing configuration YAML.
   */
  public function testTemplate(): void {
    $this->drupalGet('/admin/config/task/job/authoring/checklist/add/entity_template__create');
    $this->assertSession()->statusCodeEquals(200);
    $prefix = 'plugin_configuration[templates][0]';
    $source = $prefix . '[template][configuration]';
    $this->submitForm([
      'name' => 'create',
      'label' => 'Create record',
      $prefix . '[name]' => 'main',
    ], 'plugin_configuration_templates_0_update');
    $this->submitForm([
      $source . '[target_entity_type_id]' => 'entity_test',
    ], 'plugin_configuration_templates_0_template_configuration_update');
    $this->submitForm([
      $source . '[target_entity_bundle]' => 'entity_test',
      $source . '[label]' => 'Record',
      $source . '[parameters][0][name]' => 'title',
      $source . '[parameters][0][label]' => 'Title',
      $source . '[parameters][0][type]' => 'string',
      $source . '[parameters][0][required]' => TRUE,
      $source . '[components][0][name]' => 'name',
      $source . '[components][0][path]' => 'name.0.value',
      $source . '[components][0][id]' => 'property_context',
      $source . '[components][0][selector]' => 'title',
      $prefix . '[editor_plugin]' => 'standard',
    ], 'plugin_configuration_templates_0_update');
    $this->assertSession()->fieldExists($prefix . '[context_mapping][title]');
    $this->submitForm([
      $prefix . '[context_mapping][title]' => 'checklist:entity.title.0.value',
      $prefix . '[editor][components][0][name]' => 'name',
      $prefix . '[editor][components][0][context]' => 'entity',
      $prefix . '[editor][components][0][path]' => 'name.0.value',
      $prefix . '[editor][components][0][label]' => 'Name',
    ], 'Add');
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->submitForm([], 'Save');
    $job = $this->container->get('entity_type.manager')->getStorage('task_job')->loadUnchanged('authoring');
    $configuration = $job->getChecklistItems()['create']['handler_configuration'];
    $candidate = $configuration['templates']['main'];
    $this->assertSame('embedded', $candidate['template']['type']);
    $this->assertSame('property_context', $candidate['template']['configuration']['components']['name']['id']);
    $this->assertSame('checklist:entity.title.0.value', $candidate['context_mapping']['title']);
    $this->assertSame('standard', $candidate['editor']['plugin']);
    $this->assertSame('name.0.value', $candidate['editor']['configuration']['components']['name']['path']);
    $this->assertCount(0, $this->container->get('entity_type.manager')->getStorage('entity_test')->loadMultiple());
    $this->drupalGet('/admin/config/task/job/authoring/checklist/create/configure');
    $this->assertSession()->fieldValueEquals($source . '[parameters][0][name]', 'title');
    $this->assertSession()->fieldValueEquals($prefix . '[editor][components][0][path]', 'name.0.value');
    $this->container->get('current_user')->setAccount($this->loggedInUser);
    $task = Task::create(['title' => 'From the authored job', 'job' => $job->id()]);
    $task->save();
    $item = $task->checklist->checklist->getItem('create');
    $item->save();
    $this->drupalGet('/checklist/task/' . $task->id() . '/checklist/create/action');
    $this->assertSame(200, $this->getSession()->getStatusCode(), $this->getSession()->getPage()->getText());
    $this->assertSession()->buttonExists('Open form');
    $this->submitForm([], 'Open form');
    $this->assertSession()->fieldValueEquals('editor[values][name]', 'From the authored job');
    $this->submitForm(['editor[values][name]' => 'Reviewed record'], 'Submit');
    $this->assertSession()->pageTextContains('Entity saved.');
    $saved = $this->container->get('entity_type.manager')->getStorage('checklist_item')->loadUnchanged($item->id());
    $this->assertTrue($saved->isComplete());
    $this->assertSame('Reviewed record', $saved->get('outcomes')->get('entity')->getValue()->label());
  }

  /**
   * Apply-to selects reusable templates/forms and exports their dependencies.
   */
  public function testReferencedApply(): void {
    TemplateBuilder::create([
      'id' => 'task_record',
      'label' => 'Task record',
      'return_type' => 'entity:task:task',
    ])->save();
    $this->container->get('plugin.manager.entity_template.builder')->clearCachedDefinitions();
    TemplateBlueprint::create([
      'id' => 'task_record',
      'label' => 'Task blueprint',
      'builder' => 'config:task_record',
      'templates' => [
        'main' => [
          'id' => 'default',
          'label' => 'Update title',
          'components' => [
            'title' => ['id' => 'property_value', 'path' => 'title.0.value', 'value' => 'Updated'],
          ],
        ],
      ],
    ])->save();
    FormDefinition::create([
      'id' => 'task_editor',
      'label' => 'Task editor',
      'plugin' => 'standard',
      'configuration' => [
        'data' => ['entity' => ['plugin' => 'provided_data']],
        'components' => [
          'title' => ['component_type' => 'typed_data', 'context' => 'entity', 'path' => 'title.0.value'],
        ],
      ],
    ])->save();
    $this->drupalGet('/admin/config/task/job/authoring/checklist/add/entity_template__apply_to');
    $prefix = 'plugin_configuration[templates][0]';
    $this->submitForm([
      'name' => 'update',
      'label' => 'Update task',
      'plugin_configuration[context_mapping][target]' => 'checklist:entity',
      $prefix . '[name]' => 'main',
    ], 'plugin_configuration_templates_0_update');
    $this->submitForm([
      $prefix . '[template][type]' => 'referenced',
    ], 'plugin_configuration_templates_0_template_update');
    $this->submitForm([
      $prefix . '[template][configuration][blueprint]' => 'task_record',
    ], 'plugin_configuration_templates_0_template_update');
    $this->submitForm([
      $prefix . '[template][configuration][template_id]' => 'main',
      $prefix . '[editor_plugin]' => 'referenced',
    ], 'plugin_configuration_templates_0_update');
    $this->submitForm([$prefix . '[editor][form_id]' => 'task_editor'], 'Add');
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->submitForm([], 'Save');
    $job = $this->container->get('entity_type.manager')->getStorage('task_job')->loadUnchanged('authoring');
    $configuration = $job->getChecklistItems()['update']['handler_configuration'];
    $this->assertSame('checklist:entity', $configuration['context_mapping']['target']);
    $this->assertSame('referenced', $configuration['templates']['main']['template']['type']);
    $this->assertSame('task_editor', $configuration['templates']['main']['editor']['configuration']['form_id']);
    $this->assertContains('entity_template.blueprint.task_record', $job->getDependencies()['config']);
    $this->assertContains('flexiform.form.task_editor', $job->getDependencies()['config']);
  }

}
