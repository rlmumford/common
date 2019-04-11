<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 11/04/2019
 * Time: 15:13
 */

namespace Drupal\flexilayout_builder\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'layout_settings' field type.
 *
 * @FieldType(
 *   id = "layout_settings",
 *   label = @Translation("Layout Settings"),
 *   description = @Translation("Layout Settings"),
 *   no_ui = TRUE,
 *   cardinality = 1
 * )
 */
class LayoutSettingsItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'settings' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['settings'] = DataDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Layout Settings'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['settings'] = [];
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->settings);
  }
}
