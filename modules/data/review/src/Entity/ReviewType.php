<?php

namespace Drupal\review\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\review\ReviewTypeInterface;

/**
 * ReviewType entity class.
 *
 * @ConfigEntityType(
 *   id = "review_type",
 *   label = @Translation("Review Type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\review\Form\ReviewTypeForm",
 *       "edit" = "Drupal\review\Form\ReviewTypeForm",
 *     },
 *     "list_builder" = "Drupal\review\ReviewTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer review types",
 *   config_prefix = "review_type",
 *   bundle_of = "review",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/review_types/add",
 *     "edit-form" = "/admin/structure/review_types/manage/{review_type}",
 *     "collection" = "/admin/structure/review_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "default_uri",
 *     "default_label",
 *   }
 * )
 */
class ReviewType extends ConfigEntityBundleBase implements ReviewTypeInterface {

  /**
   * The machine name of this review type
   *
   * @var string
   */
  protected $id;

  /**
   * The human readable name of this review type.
   *
   * @var string
   */
  protected $label;

  /**
   * The target entity type.
   *
   * @var string
   */
  protected $target_entity_type_id;

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId() {
    return $this->target_entity_type_id;
  }

}
