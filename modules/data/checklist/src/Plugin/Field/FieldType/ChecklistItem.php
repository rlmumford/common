<?php

namespace Drupal\checklist\Plugin\Field\FieldType;

use Drupal\checklist\ChecklistAdaptor;
use Drupal\checklist\ChecklistInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\plugin_reference\Plugin\Field\FieldType\PluginReferenceItem;

/**
 * Checklist field item.
 *
 * @FieldType(
 *   id = "checklist",
 *   label = @Translation("Checklist"),
 *   category = @Translation("Checklist"),
 * );
 *
 * @package Drupal\checklist\Plugin\Field\FieldType
 */
class ChecklistItem extends PluginReferenceItem {

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

  /**
   * Get the actual checklist.
   *
   * @return \Drupal\checklist\ChecklistInterface|null
   *   The checklist.
   */
  public function getChecklist() : ?ChecklistInterface {
    return $this->checklist;
  }

}
