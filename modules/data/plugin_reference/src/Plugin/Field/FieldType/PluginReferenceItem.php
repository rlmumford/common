<?php

namespace Drupal\plugin_reference\Plugin\Field\FieldType;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\plugin_reference\PluginAdaptor;

/**
 * Class ChecklistItem
 *
 * @FieldType(
 *   id = "plugin_reference",
 *   label = @Translation("Plugin & Configuration"),
 *   category = @Translation("Reference"),
 * );
 *
 * @package Drupal\plugin_reference\Plugin\Field\FieldType
 */
class PluginReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'plugin_type' => NULL,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return !$this->id;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'id' => [
          'type' => 'varchar',
          'length' => 64,
        ],
        'configuration' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ]
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Plugin Id'))
      ->setRequired(TRUE);
    $properties['configuration'] = MapDataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Configuration'));
    $properties['plugin'] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Plugin'))
      ->setComputed(TRUE)
      ->setClass(PluginAdaptor::class);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    if ($property_name === 'plugin') {
      $this->id = $this->plugin->getValue()->getPluginId();
      $this->get('configuration')->setValue(
        $this->plugin instanceof ConfigurableInterface ?
          $this->plugin->getConfiguration() :
          []
      );
    }

    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (is_array($values) && isset($values['configuration']) && is_string($values['configuration'])) {
      $values['configuration'] = @unserialize($values['configuration']) ?: [];
    }

    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    // Make sure the configuration is set correctly.
    $this->get('configuration')->setValue(
      $this->plugin instanceof ConfigurableInterface ?
        $this->plugin->getConfiguration() :
        []
    );
  }
}
