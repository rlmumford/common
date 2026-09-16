<?php

namespace Drupal\typed_data_reference;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;

class TypedDataReferenceItemList extends FieldItemList {
  use TypedDataCastingTrait;

  /**
   * The list of field items.
   *
   * @var \Drupal\typed_data_reference\Plugin\Field\FieldType\TypedDataReferenceItem[]
   */
  protected $list = [];

  /**
   * The properties.
   *
   * @var \Drupal\Core\TypedData\TypedDataInterface[]
   */
  protected $properties = [];

  /**
   * The property definitions keyed by index.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[]
   */
  protected $propertyDefinitions = [];

  /**
   * Whether the properties have been built.
   *
   * @var bool
   */
  protected $propertiesBuilt = FALSE;

  /**
   * Get the property definitions associated with this typed data reference.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *  The list of property definitions.
   */
  public function getPropertyDefinitions() {
    if (!$this->propertyDefinitions) {
      $definitions = \Drupal::moduleHandler()->invokeAll(
        'typed_data_reference_property_definitions',
        [
          $this
        ]
      );
      \Drupal::moduleHandler()->alter('typed_data_reference_property_definitions', $definitions, $this);

      $this->propertyDefinitions = $definitions;
    }

    return $this->propertyDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = TRUE) {
    if (!$this->propertiesBuilt) {
      /** @var \Drupal\Core\TypedData\TypedDataInterface[] $properties */
      $properties = [];
      foreach ($this->getPropertyDefinitions() as $key => $definition) {
        $properties[$key] = $this->typedDataManager->create(
          $definition, NULL, $key, $this
        );
      }

      $values = [];
      foreach ($this->list as $item) {
        $property_name = $item->getPropertyName();

        if (empty($properties[$property_name])) {
          continue;
        }

        if (!strpos($item->key, '[') && !strpos($item->key, '.')) {
          $values[$item->key] = $this->upcastStoredValue($item->value ?? $item->blob, $this->propertyDefinitions[$item->key]);
        }
        else if (strpos($item->key, '.')) {
          $path = strpos($item->key, '[') ?
            explode('.', substr($item->key, 0, strpos($item->key, '['))) :
            explode('.', $item->key);
          $property_def = NULL;
          foreach ($path as $property_path_bit) {
            if ($property_def === NULL) {
              $property_def = $this->propertyDefinitions[$property_path_bit];
            }
            else if (
              $property_def instanceof ComplexDataDefinitionInterface &&
              $property_def->getPropertyDefinition($property_path_bit)
            ) {
              $property_def = $property_def->getPropertyDefinition($property_path_bit);
            }
            else {
              break 2;
            }
          }

          if (!strpos($item->key, '[')) {
            NestedArray::setValue(
              $values,
              $path,
              $this->upcastStoredValue($item->value ?? $item->blob, $property_def)
            );
          }
          else {
            if (!$property_def instanceof ListDataDefinitionInterface) {
              break;
            }

            $lkey = substr($item->key, strpos($item->key, '['));

            $array = NestedArray::getValue($values, $path) ?: [];
            if ($lkey === '[]') {
              $array[] = $this->upcastStoredValue(
                $item->value ?? $item->blob,
                $property_def->getItemDefinition()
              );
            }
            else {
              $lkey = str_replace(['[', ']'], '', $lkey);
              $array[is_int($lkey) ? (int)$lkey : $lkey] = $this->upcastStoredValue(
                $item->value ?? $item->blob,
                $property_def->getItemDefinition()
              );
            }
            NestedArray::setValue($values, $path, $array);
          }
        }
        else if (strpos($item->key, '[')) {
          if (!isset($values[$property_name])) {
            $values[$property_name] = [];
          }

          $lkey = substr($item->key, strpos($item->key, '['));
          if ($lkey === '[]') {
            $values[$property_name][] = $this->upcastStoredValue(
              $item->value ?? $item->blob,
              $this->propertyDefinitions[$property_name]
            );
          }
          else {
            $lkey = str_replace(['[', ']'], '', $lkey);
            $values[$property_name][is_int($lkey) ? (int)$lkey : $lkey] = $this->upcastStoredValue(
              $item->value ?? $item->blob,
              $this->propertyDefinitions[$property_name]
            );
          }
        }
      }

      foreach ($properties as $name => $property) {
        if (isset($values[$name])) {
          $test = clone $property;
          $test->setValue($values[$name]);
          if ($test->validate()->count() == 0) {
            $property->setValue($values[$name]);
          }
        }
      }

      $this->propertiesBuilt = TRUE;
      $this->properties = $properties;
    }

    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($property_name) {
    $this->getProperties();

    $property = $this->properties[$property_name];
    return $property instanceof ListInterface ? $property : $property->getValue();
  }

  /**
   * Get a property or key in the list.
   *
   * @param int|string $index
   *   To retrieve a field item, provide an int, to get a property provide a
   *   non-integer string.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|null
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function get($index) {
    if (filter_var($index, FILTER_VALIDATE_INT) !== FALSE) {
      return parent::get($index);
    }
    else {
      $this->getProperties();
      return isset($this->properties[$index]) ? $this->properties[$index] : NULL;
    }
  }

  /**
   * Set a property or key in the list.
   *
   * @param int|string $index
   *   To set a field item provide an integer, to set a property value provide a
   *   string.
   * @param mixed $value
   *   The value to set.
   * @param bool $notify
   *   Whether or not to notify the parent item.
   *
   * @return $this|\Drupal\context_reference\TypedDataReferenceItemList|\Drupal\Core\TypedData\ListInterface|\Drupal\Core\TypedData\Plugin\DataType\ItemList
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function set($index, $value, $notify = TRUE) {
    if (filter_var($index, FILTER_VALIDATE_INT) !== FALSE) {
      parent::set($index, $value);
    }
    else {
      $this->getProperties();
      if ($value instanceof TypedDataInterface) {
        $this->properties[$index]->setValue($value->getValue());
      }
      else {
        $this->properties[$index]->setValue($value);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __set($property_name, $value) {
    $this->getProperties();

    if (isset($this->propertyDefinitions[$property_name])) {
      if ($value instanceof TypedDataInterface) {
        $this->properties[$property_name] = $value;
      }
      else {
        $this->properties[$property_name]->setValue($value);
      }
    }

    $this->updateItemListForPropertySet($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function __isset($property_name) {
    $this->getProperties();

    return isset($this->properties[$property_name]) && ($this->properties[$property_name]->getValue() !== NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function __unset($property_name) {
    $this->getProperties();

    unset($this->properties[$property_name]);
    $this->updateItemListForPropertyUnset($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $values = [];
    foreach ($this->getProperties() as $name => $property) {
      $values[$name] = $property->getValue();
    }
    return $values;
  }

  /**
   *
   */
  protected function updateItemListForPropertySet($property_name) {
    // If the property hasn't been set yet then do nothing.
    if (isset($this->properties[$property_name])) {
      $property = $this->properties[$property_name];
      $this->updateItemListForPropertyUnset($property_name);
      $this->appendItemsForData($property, $property_name);
    }
  }

  protected function appendItemsForData(TypedDataInterface $data, string $key) {
    if ($data instanceof EntityAdapter) {
      $this->appendItem([
        'key' => $key,
        'value' => $data->getEntity()->id(),
      ]);
    }
    else if ($data instanceof ComplexDataInterface) {
      $this->appendItem([
        'key' => $key,
        'blob' => $data->toArray(),
      ]);

      // This is too complicated for now.
      //foreach ($data->getProperties() as $property_name => $property) {
      //  $this->appendItemsForData($property, "{$key}.{$property_name}");
      //}
    }
    else if ($data instanceof ListInterface) {
      foreach ($data as $index => $item) {
        $this->appendItemsForData($item, "{$key}[{$index}]");
      }
    }
    else {
      $this->appendItem([
        'key' => $key,
        'value' => $data->getValue(),
      ]);
    }
  }

  protected function updateItemListForPropertyUnset($property_name) {
    foreach ($this->list as $delta => $item) {
      if ($item->getPropertyName() === $property_name) {
        unset($this->list[$delta]);
      }
    }

    $this->rekey();
  }

  protected function upcastStoredValue($value, $definition) {
    return $this->upcastValue($value, $definition)->getValue();
  }

  public function onChange($delta) {
    if (filter_var($delta, FILTER_VALIDATE_INT) !== FALSE) {
      return parent::onChange($delta);
    }
    else {
      $this->updateItemListForPropertySet($delta);
    }
  }
}
