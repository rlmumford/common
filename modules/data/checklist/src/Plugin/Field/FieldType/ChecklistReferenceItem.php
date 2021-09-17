<?php

namespace Drupal\checklist\Plugin\Field\FieldType;

use Drupal\checklist\ChecklistReferenceChecklistAdaptor;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Reference a checklist on another entity.
 *
 * @FieldType(
 *   id = "checklist_reference",
 *   label = @Translation("Checklist"),
 *   description = @Translation("A Reference to a Specific Checklist"),
 *   category = @Translation("Checklist"),
 * );
 *
 * @package Drupal\checklist\Plugin\Field\FieldType
 */
class ChecklistReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['checklist_key'] = [
      'type' => 'varchar',
      'length' => '64',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['checklist_key'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Checklist Key'))
      ->setRequired(TRUE);

    $properties['checklist'] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Checklist'))
      ->setComputed(TRUE)
      ->setClass(ChecklistReferenceChecklistAdaptor::class);

    return $properties;
  }

}
