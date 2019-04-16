<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 19/11/2018
 * Time: 11:32
 */

namespace Drupal\task\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Definition of different bundle of tasks
 *
 * @ConfigEntityType(
 *   id = "task_bundle",
 *   label = @Translation("Task Bundle"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "form" = {
 *       "add" = "Drupal\task\Form\TaskBundleForm",
 *       "edit" = "Drupal\task\Form\TaskBundleForm",
 *     },
 *     "list_builder" = "Drupal\task\TaskBundleListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer task bundles",
 *   config_prefix = "task.task_bundle",
 *   bundle_of = "task",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/task_bundle/add",
 *     "edit-form" = "/admin/structure/task_bundle/manage/{task_bundle}",
 *     "collection" = "/admin/structure/task_bundle",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   }
 * )
 */
class TaskBundle extends ConfigEntityBundleBase {

  public $id;

  public $label;

}
