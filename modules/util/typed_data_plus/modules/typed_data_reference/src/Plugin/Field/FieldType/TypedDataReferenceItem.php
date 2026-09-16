<?php

namespace Drupal\typed_data_reference\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Item class for context reference items.
 *
 * @FieldType(
 *   id = "typed_data_reference",
 *   label = @Translation("Typed Data Reference"),
 *   category = @Translation("Reference"),
 *   list_class = "\Drupal\typed_data_reference\TypedDataReferenceItemList"
 * );
 *
 * @property string $key
 *   The key being stored in this field item.
 * @property string $value
 *   The value being stored in this field item.
 * @property mixed $blob
 *   The value being stored in this field item as a blob.
 *
 * @package Drupal\context_reference\Plugin\Field\FieldType
 */
class TypedDataReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'key' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Key'))
        ->setRequired(TRUE),
      'value' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Value (Raw)')),
      'blob' => DataDefinition::create('any')
        ->setLabel(new TranslatableMarkup('Value (Blob)')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'key' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'value' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'blob' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Get the property name of this typed data item.
   *
   * @return string
   *   The property name.
   */
  public function getPropertyName() {
    $key = $this->key;
    if (strpos($key, '[')) {
      [$key,] = explode('[', $key);
    }
    if (strpos($key, '.')) {
      [$key,] = explode('.', $key);
    }

    return $key;
  }
}
