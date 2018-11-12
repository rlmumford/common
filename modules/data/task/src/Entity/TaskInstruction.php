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
 *   id = "task_instruction",
 *   label = @Translation("Task Instruction "),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\task\Form\TaskInstructionForm",
 *       "edit" = "Drupal\task\Form\TaskInstructionForm",
 *     },
 *     "list_builder" = "Drupal\task\TaskInstructionListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer task instructions",
 *   config_prefix = "task_instruction",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/task_instruction/add",
 *     "edit-form" = "/admin/structure/task_instruction/manage/{task_instruction}",
 *     "collection" = "/admin/structure/task_instruction",
 *   },
 *   config_export = {
 *     "id",
 *     "code",
 *     "label",
 *     "bundle",
 *     "admin_description",
 *     "instruction",
 *     "default_title",
 *     "steps",
 *   }
 * )
 */
class TaskInstruction extends ConfigEntityBase {

}
