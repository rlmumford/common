<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Entity\IdentityDataType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Entity class for IdentityDatas.
 *
 * @ContentEntityType(
 *   id = "identity_data",
 *   label = @Translation("Identity Data"),
 *   label_singular = @Translation("identity data"),
 *   label_plural = @Translation("identity datas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count identity data",
 *     plural = "@count identity data"
 *   ),
 *   bundle_label = @Translation("Identity Data Type"),
 *   handlers = {
 *     "storage" = "Drupal\identity\IdentityDataStorage",
 *     "access" = "Drupal\identity\IdentityDataAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "identity_data",
 *   revision_table = "identity_data_revision",
 *   admin_permission = "administer identity data",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   has_notes = "true",
 *   bundle_plugin_type = "identity_data_type",
 *   field_ui_base_route = "entity.identity_type.edit_form",
 *   links = {
 *     "canonical" = "/identity/data/{identity_data}",
 *     "edit-form" = "/identity/data/{identity_data}/edit"
 *   }
 * )
 */
class IdentityData extends ContentEntityBase implements IdentityDataInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback('\Drupal\identity_data\Entity\IdentityData::createLabel')
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active?'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\identity_data\Entity\IdentityData::getCurrentUserId')
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

    $fields['identity_data'] = BaseFieldDefinition::create('identity_data_reference')
      ->setLabel(t('IdentityData'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'identity_data')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['manager'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Manager'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\identity_data\Entity\IdentityData::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['recipients'] = BaseFieldDefinition::create('entity_reference')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setLabel(t('Recipients'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}

