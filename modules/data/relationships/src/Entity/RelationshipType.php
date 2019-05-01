<?php

namespace Drupal\relationships\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Definition of different bundle of contact relationships
 *
 * @ConfigEntityType(
 *   id = "relationship_type",
 *   label = @Translation("Relationship Type"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "form" = {
 *       "add" = "Drupal\relationships\Form\RelationshipTypeForm",
 *       "edit" = "Drupal\relationships\Form\RelationshipTypeForm",
 *     },
 *     "list_builder" = "Drupal\relationships\Entity\RelationshipTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer relationship types",
 *   config_prefix = "relationship_type",
 *   bundle_of = "relationship",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/relationship_type/add",
 *     "edit-form" = "/admin/structure/relationship_type/manage/{relationship_type}",
 *     "collection" = "/admin/structure/relationship_type",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "forward_label",
 *     "backward_label",
 *     "tail_entity_type_id",
 *     "tail_label",
 *     "tail_handler",
 *     "tail_handler_settings",
 *     "tail_field",
 *     "tail_field_label",
 *     "head_entity_type_id",
 *     "head_label",
 *     "head_handler",
 *     "head_handler_settings",
 *     "head_field",
 *     "head_field_label",
 *   }
 * )
 */
class RelationshipType extends ConfigEntityBundleBase {

  public $id;

  public $label;

  public $forward_label;

  public $backward_label;

  public $tail_entity_type_id;

  public $tail_label;

  public $tail_handler;

  public $tail_handler_settings;

  public $tail_field;

  public $tail_field_label;

  public $head_entity_type_id;

  public $head_label;

  public $head_handler;

  public $head_handler_settings;

  public $head_field;

  public $head_field_label;

  /**
   * Get the label of a given end.
   *
   * @param string $end
   *   Either head or tail
   *
   * @return string
   */
  public function getEndLabel($end) {
    return $this->{$end."_label"};
  }

  /**
   * Get the entity type id for one end.
   *
   * @param $end
   *
   * @return string
   */
  public function getEndEntityTypeId($end) {
    return $this->{$end."_entity_type_id"};
  }

  /**
   * Get the selection handler plugin for one end of this relationship type.
   *
   * @param $end
   *
   * @return \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
   */
  public function getEndHandlerPlugin($end) {
    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.entity_reference_selection');

    $options = $this->getEndHandlerSettings($end);
    $options += [
      'handler' => $this->getEndHandler($end),
      'target_type' => $this->getEndEntityTypeId($end),
    ];
    return $manager->getInstance($options);
  }

  public function getEndHandler($end) {
    return $this->{$end."_handler"};
  }

  public function getEndHandlerSettings($end) {
    return $this->{$end."_handler_settings"};
  }

  public function getEndHandlerSetting($end, $setting) {
    $settings = $this->getEndHandlerSettings($end);
    return NestedArray::getValue($settings, is_array($setting) ? $setting : [$setting]);
  }

}
