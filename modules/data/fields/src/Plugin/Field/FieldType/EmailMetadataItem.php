<?php

namespace Drupal\rlm_fields\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EmailItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'email_metadata' field type.
 *
 * @FieldType(
 *   id = "email_metadata",
 *   label = @Translation("Email (with metadata)"),
 *   description = @Translation("An entity field containing an email value."),
 *   default_widget = "email_metadata_default",
 *   default_formatter = "basic_string"
 * )
 */
class EmailMetadataItem extends EmailItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
        'label_max_length' => 255,
        'label_is_ascii' => FALSE,
        'label_case_sensitive' => FALSE,
      ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['label'] = [
      'type' => $field_definition->getSetting('label_is_ascii') === TRUE ? 'varchar_ascii' : 'varchar',
      'length' => (int) $field_definition->getSetting('label_max_length'),
      'binary' => $field_definition->getSetting('label_case_sensitive'),
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setSetting('case_sensitive', $field_definition->getSetting('label_case_sensitive'));

    return $properties;
  }
}
