<?php

namespace Drupal\Tests\checklist_communication\Functional;

use Drupal\task_job\Entity\Job;
use Drupal\task\Entity\Task;
use Drupal\Tests\BrowserTestBase;

/**
 * Authors one item and completes its editor and operation confirmation.
 *
 * @group checklist_communication
 */
class CommunicationAuthoringTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['task_job', 'checklist_communication_ui', 'checklist_communication_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The nested configuration persists and drives the shared runtime editor.
   */
  public function testAuthorAndConfirm(): void {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    Job::create(['id' => 'authoring', 'label' => 'Authoring'])->save();
    $this->drupalGet('/admin/config/task/job/authoring/checklist/add/entity_template__create_communication');
    $this->assertSession()->statusCodeEquals(200);
    $prefix = 'plugin_configuration[templates][0]';
    $source = $prefix . '[template][configuration]';
    $this->submitForm([
      'name' => 'prepare',
      'label' => 'Prepare communication',
      $prefix . '[name]' => 'email',
    ], 'plugin_configuration_templates_0_update');
    $this->submitForm([
      $source . '[target_entity_type_id]' => 'communication',
    ], 'plugin_configuration_templates_0_template_configuration_update');
    $this->submitForm([
      $source . '[target_entity_bundle]' => 'email',
      $source . '[label]' => 'Welcome email',
      $source . '[components][0][name]' => 'subject',
      $source . '[components][0][path]' => 'subject.0.value',
      $source . '[components][0][id]' => 'property_value',
      $source . '[components][0][value]' => 'Welcome',
      $prefix . '[editor_plugin]' => 'standard',
    ], 'plugin_configuration_templates_0_update');
    $this->submitForm([
      $prefix . '[editor][components][0][name]' => 'subject',
      $prefix . '[editor][components][0][context]' => 'entity',
      $prefix . '[editor][components][0][path]' => 'subject.0.value',
      $prefix . '[editor][components][0][label]' => 'Subject',
      $prefix . '[operation][id]' => 'recorded',
      $prefix . '[operation][confirm]' => TRUE,
      $prefix . '[operation][label]' => 'Confirm welcome email',
    ], 'Add');
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->submitForm([], 'Save');
    $job = $this->container->get('entity_type.manager')->getStorage('task_job')->loadUnchanged('authoring');
    $candidate = $job->getChecklistItems()['prepare']['handler_configuration']['templates']['email'];
    $this->assertSame('recorded', $candidate['operation']['id']);
    $this->assertTrue($candidate['operation']['confirm']);
    $this->assertSame('standard', $candidate['editor']['plugin']);
    $this->assertContains('checklist_communication_test', $job->getDependencies()['module']);
    $this->drupalGet('/admin/config/task/job/authoring/checklist/prepare/configure');
    $this->assertSession()->fieldValueEquals($prefix . '[operation][label]', 'Confirm welcome email');

    $this->container->get('current_user')->setAccount($this->loggedInUser);
    $task = Task::create(['title' => 'Welcome Alex', 'job' => $job->id()]);
    $task->save();
    $item = $task->checklist->checklist->getItem('prepare');
    $item->save();
    $this->drupalGet('/checklist/task/' . $task->id() . '/checklist/prepare/action');
    $this->submitForm([], 'Open form');
    $this->assertSession()->fieldValueEquals('editor[values][subject]', 'Welcome');
    $this->submitForm(['editor[values][subject]' => 'Welcome Alex'], 'Submit');
    $this->assertSession()->pageTextContains('Entity saved.');
    $this->drupalGet('/task/' . $task->id());
    $this->assertSession()->pageTextContains('Confirm welcome email');
    $this->assertSession()->elementsCount('css', '[data-resource-key^="communication:"]', 1);
    $this->assertSession()->elementTextContains('css', '.checklist-communication-review', 'Welcome Alex');
    $storage = $this->container->get('entity_type.manager')->getStorage('checklist_item');
    $children = $storage->loadByProperties(['name' => 'communication__' . str_replace('-', '', $item->uuid())]);
    $this->assertCount(1, $children);
    $child = reset($children);
    $this->container->get('checklist.item_executor')->submit($child);
    $this->drupalGet('/checklist/task/' . $task->id() . '/checklist/' . $child->getName() . '/action');
    $this->assertSession()->buttonExists('Confirm operation');
    $this->submitForm([], 'Confirm operation');
    $this->assertSession()->statusCodeEquals(200);
    $attempt = $this->container->get('checklist.attempt_journal')->latest($child);
    $this->container->get('checklist.item_executor')->run($attempt);
    $this->assertTrue($storage->loadUnchanged($child->id())->isComplete());
    $this->drupalGet('/task/' . $task->id());
    $this->assertSession()->elementsCount('css', '[data-resource-key^="communication:"]', 1);
    $this->assertSession()->elementTextContains('css', '.checklist-communication-review', 'Sent');
  }

}
