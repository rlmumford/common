<?php

namespace Drupal\rlm_fields\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\LanguageItem;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Class SpokenLanguageItem
 *
 * @FieldType(
 *   id = "spoken_language",
 *   label = @Translation("Spoken Language"),
 *   description = @Translation("A language field with proficiency metadata."),
 *   default_widget = "spoken_language_select",
 *   default_formatter = "spoken_language",
 * )
 *
 * @package Drupal\rlm_fields\Plugin\Field\FieldType
 */
class SpokenLanguageItem extends LanguageItem {

  /**
   * Proficiency Constants
   */
  const PROF_MOTHER = 'mother';
  const PROF_FLUENT = 'fluent';
  const PROF_PART = 'partial';

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['proficiency'] = [
      'type' => 'varchar_ascii',
      'length' => 12,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['proficiency'] = DataDefinition::create('string')
      ->setLabel(t('Proficiency'))
      ->addConstraint('Length', ['max' => 12]);

    return $properties;
  }

}
