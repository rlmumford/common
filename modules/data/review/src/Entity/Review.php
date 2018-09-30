<?php

namespace Drupal\review\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\review\ReviewInterface;
use Drupal\review\Entity\ReviewType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entity class for reviews.
 *
 * @ContentEntityType(
 *   id = "review",
 *   label = @Translation("Review"),
 *   label_singular = @Translation("review"),
 *   label_plural = @Translation("reviews"),
 *   label_count = @PluralTranslation(
 *     singular = "@count review",
 *     plural = "@count reviews"
 *   ),
 *   bundle_label = @Translation("Review Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\review\ReviewListBuilder",
 *     "storage" = "Drupal\review\ReviewStorage",
 *     "access" = "Drupal\review\reviewAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\review\Form\ReviewForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "review",
 *   revision_table = "review_revision",
 *   admin_permission = "administer reviews",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   bundle_entity_type = "review_type",
 *   field_ui_base_route = "entity.review_type.edit_form",
 *   links = {
 *     "collection" = "/review",
 *     "canonical" = "/review/{review}",
 *     "edit-form" = "/review/{review}/edit"
 *   }
 * )
 */
class Review extends ContentEntityBase implements ReviewInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['target'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Target'))
      ->setDescription(new TranslatableMarkup('The entity being reviewed.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['score'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Score'))
      ->setRequired(TRUE)
      ->setSetting('min', 0)
      ->setSetting('max', 10)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\review\Entity\review::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    if ($review_type = ReviewType::load($bundle)) {
      $fields['target'] = clone $base_field_definitions['target'];
      $fields['target']->setSetting('target_type', $review_type->getTargetEntityTypeId());
      return $fields;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return ReviewType::load($this->bundle());
  }
}

