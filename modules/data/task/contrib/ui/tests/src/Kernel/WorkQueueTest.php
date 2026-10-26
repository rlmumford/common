<?php

namespace Drupal\Tests\task_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\task\Entity\Task;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

/**
 * Exercises query access, pagination and shared readiness in the workspace.
 *
 * @group task_ui
 */
class WorkQueueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'text', 'filter', 'options', 'datetime', 'entity',
    'task', 'task_ui', 'task_checklist', 'checklist', 'plugin_reference',
    'typed_data', 'typed_data_plus', 'typed_data_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('task_checklist', ['task_checklist_request']);
    foreach (['user', 'task', 'checklist_item'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installConfig(['system', 'user']);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Only readable tasks contribute rows or pager totals.
   */
  public function testAccessPaginationAndReadiness(): void {
    User::create(['uid' => 1, 'name' => 'root', 'status' => 1])->save();
    Role::create(['id' => 'worker', 'label' => 'Worker', 'permissions' => ['view assigned tasks']])->save();
    $user = User::create(['name' => 'worker', 'roles' => ['worker'], 'status' => 1]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);
    Task::create(['title' => 'Inaccessible', 'assignee' => 1])->save();
    for ($i = 0; $i < 26; $i++) {
      Task::create(['title' => 'Review ' . $i, 'assignee' => $user->id()])->save();
    }
    $queue = $this->container->get('task_ui.queue');
    $page = $queue->build('team');
    $this->assertCount(25, $page['active']['#rows']);
    $this->assertSame(26, $this->container->get('pager.manager')->getPager()->getTotalItems());
    $this->assertArrayHasKey('empty', $queue->build('unassigned'));
    $future = Task::create([
      'title' => 'Scheduled review',
      'assignee' => $user->id(),
      'start' => gmdate('Y-m-d\TH:i:s', time() + 3600),
    ]);
    $future->save();
    $page = $queue->build('mine', 'Scheduled');
    $this->assertCount(1, $page['pending']['#rows']);
    $future->resolve()->save();
    $this->assertArrayHasKey('empty', $queue->build('mine', 'Scheduled'));
    $this->assertCount(1, $queue->build('history', 'Scheduled')['resolved']['#rows']);
  }

}
