<?php

namespace Drupal\project\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\project\Entity\ProjectTypeInterface;

/**
 * ProjectType entity class.
 *
 * @ConfigEntityType(
 *   id = "project_type",
 *   label = @Translation("Project Type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\project\Form\ProjectTypeForm",
 *       "edit" = "Drupal\project\Form\ProjectTypeForm",
 *     },
 *     "list_builder" = "Drupal\project\Entity\ProjectTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer project types",
 *   config_prefix = "project_type",
 *   bundle_of = "project",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/project_types/add",
 *     "edit-form" = "/admin/structure/project_types/manage/{project_type}",
 *     "collection" = "/admin/structure/project_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "default_uri",
 *     "default_label",
 *   }
 * )
 */
class ProjectType extends ConfigEntityBundleBase implements ProjectTypeInterface {

  /**
   * The machine name of this project type
   *
   * @var string
   */
  protected $id;

  /**
   * The human readable name of this project type.
   *
   * @var string
   */
  protected $label;

  /**
   * The default uri of projects of this type.
   *
   * @var string
   */
  protected $default_uri;

  /**
   * The default label of projects of this type.
   *
   * @var string
   */
  protected $default_label;

}
