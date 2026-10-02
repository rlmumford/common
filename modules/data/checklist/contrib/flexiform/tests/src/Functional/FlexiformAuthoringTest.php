<?php

namespace Drupal\Tests\checklist_flexiform\Functional;

use Drupal\flexiform\Entity\FormDefinition;
use Drupal\task\Entity\Task;
use Drupal\task_job\Entity\Job;
use Drupal\Tests\BrowserTestBase;

/**
 * Authors a dedicated form item and completes it through the browser.
 *
 * @group checklist_flexiform
 */
class FlexiformAuthoringTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['task_job', 'checklist_flexiform_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Referenced form input mappings and outcomes survive the shared job draft.
   */
  public function testReferencedFormAuthoring(): void {
    $account = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($account);
    Job::create(['id' => 'authoring', 'label' => 'Authoring'])->save();
    FormDefinition::create([
      'id' => 'details',
      'label' => 'Task details',
      'plugin' => 'standard',
      'configuration' => [
        'data' => [
          'task' => [
            'plugin' => 'provided',
            'entity_type' => 'task',
            'bundle' => 'task',
            'save_on_submit' => TRUE,
          ],
        ],
        'components' => [
          'title' => [
            'component_type' => 'typed_data',
            'context' => 'task',
            'path' => 'title.0.value',
            'label' => 'Title',
          ],
        ],
      ],
    ])->save();
    $this->drupalGet('/admin/config/task/job/authoring/checklist/add/flexiform');
    $this->assertSession()->statusCodeEquals(200);
    // Switching type first rebuilds its controls, without committing a job.
    $this->submitForm([
      'name' => 'details',
      'label' => 'Review task details',
      'plugin_configuration[form_plugin]' => 'referenced',
    ], 'plugin_configuration_update');
    $this->submitForm([
      'plugin_configuration[display][form_id]' => 'details',
    ], 'plugin_configuration_update');
    $this->assertSession()->fieldExists('plugin_configuration[context_mapping][task]');
    $this->submitForm([
      'plugin_configuration[context_mapping][task]' => 'checklist:entity',
      'plugin_configuration[outcomes][task]' => 'reviewed_task',
    ], 'Add');
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->assertCount(0, Job::load('authoring')->getChecklistItems());
    $this->submitForm([], 'Save');
    $job = $this->container->get('entity_type.manager')->getStorage('task_job')->loadUnchanged('authoring');
    $settings = $job->getChecklistItems()['details']['handler_configuration'];
    $this->assertSame('referenced', $settings['form']['plugin']);
    $this->assertSame('details', $settings['form']['configuration']['form_id']);
    $this->assertSame('checklist:entity', $settings['context_mapping']['task']);
    $this->assertSame(['reviewed_task' => 'task'], $settings['outcomes']);
    $this->container->get('current_user')->setAccount($account);
    $task = Task::create([
      'title' => 'Original title',
      'description' => 'Review these details.',
      'job' => $job,
      'status' => 'active',
    ]);
    $task->save();
    $item = $task->checklist->checklist->getItem('details');
    $this->drupalGet('/checklist/task/' . $task->id() . '/checklist/details/action');
    $this->submitForm([], 'Open form');
    $this->assertSession()->fieldValueEquals('editor[values][title]', 'Original title');
    $this->submitForm(['editor[values][title]' => 'Reviewed title'], 'Submit');
    $this->assertSession()->pageTextContains('Form completed.');
    $saved = $this->container->get('entity_type.manager')->getStorage('task')->loadUnchanged($task->id());
    $this->assertSame('Reviewed title', $saved->label());
    $item = $saved->checklist->checklist->getItem('details');
    $this->assertTrue($item->isComplete());
    $this->assertSame($task->id(), $item->get('outcomes')->get('reviewed_task')->getValue()->id());
  }

}
