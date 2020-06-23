<?php

namespace Drupal\rlm_fields\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\telephone\Plugin\Field\FieldType\TelephoneItem;

/**
 * Class TelephoneMetadataItem
 *
 * @FieldType(
 *   id = "telephone_metadata",
 *   label = @Translation("Telephone number (with metadata)"),
 *   description = @Translation("This field stores a telephone number with metadata in the database."),
 *   category = @Translation("Number"),
 *   default_widget = "telephone_metadata_default",
 *   default_formatter = "basic_string"
 * )
 *
 * @package Drupal\rlm_fields\Plugin\Field\FieldType
 */
class TelephoneMetadataItem extends TelephoneItem {

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
    $schema['columns']['sms'] = [
      'type' => 'int',
      'size' => 'tiny',
    ];
    $schema['columns']['vm'] = [
      'type' => 'int',
      'size' => 'tiny',
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
    $properties['sms'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Can receive SMS'));
    $properties['vm'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Can receive Voice Mail'));

    return $properties;
  }

}
