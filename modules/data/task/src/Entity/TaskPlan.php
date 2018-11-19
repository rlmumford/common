<?php
/**
 * Created by PhpStorm.
 * User: Mumford
 * Date: 10/11/2018
 * Time: 16:02
 */

namespace Drupal\task\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Instructions for how to complete a given task.
 *
 * @ConfigEntityType(
 *   id = "task_plan",
 *   label = @Translation("Task Plan"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\task\Form\TaskPlanForm",
 *       "edit" = "Drupal\task\Form\TaskPlanForm",
 *     },
 *     "list_builder" = "Drupal\task\TaskPlanListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer task plans",
 *   config_prefix = "task_plan",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/task_plan/add",
 *     "edit-form" = "/admin/structure/task_plan/manage/{task_plan}",
 *     "collection" = "/admin/structure/task_plan",
 *   },
 *   config_export = {
 *     "id",
 *     "code",
 *     "label",
 *     "bundle",
 *     "description",
 *     "instructions",
 *     "default_title",
 *     "steps",
 *   }
 * )
 */
class TaskPlan extends ConfigEntityBase implements TaskPlanInterface {

  /**
   * Create a new task for this task plan.
   *
   * @return \Drupal\task\Entity\Task
   */
  public function createTask() {
    $storage = \Drupal::entityTypeManager()->getStorage('task');

    /** @var \Drupal\task\Entity\Task $task */
    $task = $storage->create([
      'type' => $this->get('bundle'),
    ]);
    $task->plan = $this;

    return $task;
  }
}
