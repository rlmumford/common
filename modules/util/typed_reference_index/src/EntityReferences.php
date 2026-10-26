<?php

namespace Drupal\typed_reference_index;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\typed_data_reference\TypedDataReferenceItemList;

/**
 * Reads entity identities from stored, explicitly typed contexts and outcomes.
 *
 * Does not hydrate entities or recurse into their fields. Untyped scalar IDs
 * cannot establish a relationship, and missing entities retain their identity.
 */
final class EntityReferences {

  /**
   * Yields identities, paths and labels for declared entity references.
   */
  public function collect(TypedDataReferenceItemList $field): iterable {
    if ($field->isEmpty()) {
      return;
    }
    $definitions = $field->getPropertyDefinitions();
    foreach ($field->getValue() as $delta => $item) {
      $key = $item['key'] ?? '';
      $parts = preg_split('/[.\[\]]/', $key, -1, PREG_SPLIT_NO_EMPTY);
      $definition = $definitions[array_shift($parts)] ?? NULL;
      foreach ($parts as $part) {
        if ($definition instanceof EntityDataDefinitionInterface) {
          // A reference to an entity is a boundary, not its property graph.
          $definition = NULL;
          break;
        }
        $definition = match (TRUE) {
          $definition instanceof ListDataDefinitionInterface => $definition->getItemDefinition(),
          $definition instanceof ComplexDataDefinitionInterface => $definition->getPropertyDefinition($part),
          default => NULL,
        };
      }
      if ($definition instanceof ListDataDefinitionInterface && str_ends_with($key, '[]')) {
        $definition = $definition->getItemDefinition();
      }
      if ($definition) {
        yield from $this->walk($definition, $item['value'] ?? $item['blob'] ?? NULL, str_replace('[]', '[' . $delta . ']', $key));
      }
    }
  }

  /**
   * Walks defined complex/list values without interpreting arbitrary integers.
   */
  private function walk(DataDefinitionInterface $definition, mixed $value, string $path): iterable {
    if ($value === NULL || $value === '') {
      return;
    }
    if ($definition instanceof DataReferenceDefinitionInterface) {
      $definition = $definition->getTargetDefinition();
      $value = is_array($value) ? ($value['target_id'] ?? NULL) : $value;
    }
    if ($definition instanceof EntityDataDefinitionInterface) {
      if ($value instanceof EntityInterface && $value->getEntityTypeId() !== $definition->getEntityTypeId()) {
        throw new \UnexpectedValueException('An entity context must match its declared entity type.');
      }
      $id = $value instanceof EntityInterface ? $value->id() : $value;
      if (!is_scalar($id) || is_bool($id)) {
        throw new \UnexpectedValueException('A stored entity context must contain an entity ID.');
      }
      if ((string) $id !== '') {
        yield [
          'entity_type' => $definition->getEntityTypeId(),
          'entity_id' => (string) $id,
          'path' => $path,
          'label' => (string) ($definition->getLabel() ?: $path),
        ];
      }
      return;
    }
    if (!is_array($value)) {
      return;
    }
    if ($definition instanceof ListDataDefinitionInterface) {
      foreach ($value as $index => $child) {
        yield from $this->walk($definition->getItemDefinition(), $child, $path . '[' . $index . ']');
      }
    }
    elseif ($definition instanceof ComplexDataDefinitionInterface) {
      foreach ($value as $name => $child) {
        if ($property = $definition->getPropertyDefinition($name)) {
          yield from $this->walk($property, $child, $path . '.' . $name);
        }
      }
    }
  }

}
