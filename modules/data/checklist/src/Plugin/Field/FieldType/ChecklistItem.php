<?php

namespace Drupal\checklist\Plugin\Field\FieldType;

use Drupal\checklist\ChecklistAdaptor;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Class ChecklistItem
 *
 * @FieldType(
 *   id = "checklist",
 *   label = @Translation("Checklist"),
 *   category = @Translation("Checklist"),
 * );
 *
 * @package Drupal\checklist\Plugin\Field\FieldType
 */
class ChecklistItem extends PluginItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'plugin_type' => 'checklist_type',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['checklist'] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Checklist'))
      ->setComputed(TRUE)
      ->setClass(ChecklistAdaptor::class);

    return $properties;
  }

}
