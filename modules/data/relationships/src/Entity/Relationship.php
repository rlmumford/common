<?php

namespace Drupal\relationships\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Relationship Entity.
 *
 * @ContentEntityType(
 *   id = "relationship",
 *   label = @Translation("Relationship"),
 *   label_singular = @Translation("relationship"),
 *   label_plural = @Translation("relationships"),
 *   label_count = @PluralTranslation(
 *     singular = "@count relationship",
 *     plural = "@count relationships"
 *   ),
 *   bundle_label = @Translation("Relationship Type"),
 *   handlers = {
 *     "storage" = "Drupal\relationships\Entity\RelationshipStorage",
 *     "access" = "Drupal\relationships\Entity\RelationshipAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\relationships\Form\RelationshipForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   has_notes = "true",
 *   base_table = "relationship",
 *   revision_table = "relationship_revision",
 *   data_table = "relationship_data",
 *   revision_data_table = "relationship_revision_data",
 *   admin_permission = "administer relationships",
 *   bundle_entity_type = "relationship_type",
 *   field_ui_base_route = "entity.relationship_type.edit_form",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   }
 * )
 */
class Relationship extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['tail'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Tail'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['head'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Head'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Active'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $bundle_fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    /** @var \Drupal\relationships\Entity\RelationshipType $type */
    $type = \Drupal::entityTypeManager()->getStorage('relationship_type')->load($bundle);
    $bundle_fields['tail'] = $base_field_definitions['tail'];
    $bundle_fields['tail']->setSetting('target_type', $type->tail_entity_type_id);
    $bundle_fields['tail']->setLabel(new TranslatableMarkup($type->tail_label));

    $bundle_fields['head'] = $base_field_definitions['head'];
    $bundle_fields['head']->setSetting('target_type', $type->head_entity_type_id);
    $bundle_fields['head']->setLabel(new TranslatableMarkup($type->head_label));

    return $bundle_fields;
  }

  /**
   * Get the relationship type entity.
   *
   * @return \Drupal\relationships\Entity\RelationshipType
   */
  public function getRelationshipType() {
    return $this->type->entity;
  }
}
