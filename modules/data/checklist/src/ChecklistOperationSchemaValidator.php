<?php

namespace Drupal\checklist;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Validates checklist operation contracts using JSON Schema Draft 7.
 */
class ChecklistOperationSchemaValidator {

  /**
   * The JSON Schema dialect used by checklist operations.
   */
  public const DIALECT = 'http://json-schema.org/draft-07/schema#';

  /**
   * Validates operation input, throwing a safe client error on mismatch.
   *
   * @param array|\stdClass $parameters
   *   Operation parameters.
   * @param array $schema
   *   The operation's parameter schema.
   *
   * @throws \Drupal\checklist\ChecklistOperationInputException
   *   When parameters do not satisfy the schema.
   */
  public function validateInput(
    array|\stdClass $parameters,
    array $schema,
  ): void {
    if (!$this->isValid($this->jsonObject($parameters), $schema)) {
      throw new ChecklistOperationInputException(
        'Operation parameters do not match the required schema.',
      );
    }
  }

  /**
   * Validates an operation result when a result schema is declared.
   *
   * @param array $result
   *   The handler's structured result.
   * @param array $schema
   *   The operation's result schema.
   *
   * @throws \UnexpectedValueException
   *   When the handler returns a result that breaks its advertised contract.
   */
  public function validateResult(array $result, array $schema): void {
    if (!$this->isValid($this->jsonValue($result), $schema)) {
      throw new \UnexpectedValueException(
        'Checklist operation result does not match its declared schema.',
      );
    }
  }

  /**
   * Converts validated operation parameters to the handler's array contract.
   */
  public function toArray(array|\stdClass $parameters): array {
    return $this->phpArray($parameters);
  }

  /**
   * Adds the dialect marker to a schema for clients and validates its shape.
   *
   * @param array $schema
   *   A JSON Schema definition.
   *
   * @return array
   *   The schema with its dialect explicitly declared.
   *
   * @throws \UnexpectedValueException
   *   When the definition declares a different dialect or is malformed.
   */
  public function exposeSchema(array $schema): array {
    if (isset($schema['$schema']) && $schema['$schema'] !== self::DIALECT) {
      throw new \UnexpectedValueException(
        'Checklist operation schemas must use JSON Schema Draft 7.',
      );
    }
    $schema['$schema'] = self::DIALECT;
    $schema_types = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];
    if (!isset($schema['type']) || !in_array($schema['type'], $schema_types, TRUE)) {
      throw new \UnexpectedValueException(
        'Checklist operation schemas must declare a valid JSON Schema type.',
      );
    }
    $validator = new Validator();
    $probe = new \stdClass();
    $errors = $validator->validate(
      $probe,
      $this->schemaObject($schema),
      Constraint::CHECK_MODE_STRICT | Constraint::CHECK_MODE_VALIDATE_SCHEMA,
    );
    if ($errors & Validator::ERROR_SCHEMA_VALIDATION) {
      throw new \UnexpectedValueException(
        'Checklist operation schema is not a valid JSON Schema Draft 7 definition.',
      );
    }
    return $schema;
  }

  /**
   * Checks a JSON-compatible value against a Draft 7 schema.
   */
  protected function isValid(mixed $value, array $schema): bool {
    $schema = $this->exposeSchema($schema);
    $schema_object = $this->schemaObject($schema);
    $validator = new Validator();
    $validator->validate(
      $value,
      $schema_object,
      Constraint::CHECK_MODE_STRICT | Constraint::CHECK_MODE_VALIDATE_SCHEMA,
    );
    return $validator->isValid();
  }

  /**
   * Converts PHP arrays into JSON object/list values without losing nesting.
   */
  protected function jsonObject(array|\stdClass $value): \stdClass {
    $object = $value instanceof \stdClass ? $value : (object) $value;
    return $this->normalizeObject($object);
  }

  /**
   * Converts a PHP value to the equivalent JSON object/list representation.
   */
  protected function jsonValue(mixed $value): mixed {
    if ($value instanceof \stdClass) {
      return $this->normalizeObject($value);
    }
    return is_array($value) ? $this->normalizeArray($value) : $value;
  }

  /**
   * Converts object properties recursively while preserving JSON lists.
   */
  protected function normalizeObject(\stdClass $value): \stdClass {
    foreach ($value as $key => $property) {
      if ($property instanceof \stdClass) {
        $value->{$key} = $this->normalizeObject($property);
      }
      elseif (is_array($property)) {
        $value->{$key} = $this->normalizeArray($property);
      }
    }
    return $value;
  }

  /**
   * Converts nested associative arrays to objects, leaving lists as lists.
   */
  protected function normalizeArray(array $value): array|\stdClass {
    if (!array_is_list($value)) {
      return $this->normalizeObject((object) $value);
    }
    foreach ($value as $key => $item) {
      if ($item instanceof \stdClass) {
        $value[$key] = $this->normalizeObject($item);
      }
      elseif (is_array($item)) {
        $value[$key] = $this->normalizeArray($item);
      }
    }
    return $value;
  }

  /**
   * Converts JSON objects to associative arrays for handler implementations.
   */
  protected function phpArray(array|\stdClass $value): array {
    if ($value instanceof \stdClass) {
      $value = get_object_vars($value);
    }
    foreach ($value as $key => $item) {
      if ($item instanceof \stdClass) {
        $value[$key] = $this->phpArray($item);
      }
      elseif (is_array($item)) {
        $value[$key] = $this->phpArray($item);
      }
    }
    return $value;
  }

  /**
   * Converts a schema's associative maps into objects as JSON Schema expects.
   */
  protected function schemaObject(array $schema): \stdClass {
    $schema = $this->normalizeSchemaValue($schema, '');
    return $schema instanceof \stdClass ? $schema : (object) $schema;
  }

  /**
   * Recursively normalizes a schema and its map-valued keywords.
   */
  protected function normalizeSchemaValue(mixed $value, string $parent_key): mixed {
    $schema_keywords = [
      'additionalItems',
      'additionalProperties',
      'contains',
      'else',
      'if',
      'not',
      'propertyNames',
      'then',
    ];
    if ($value === [] && in_array($parent_key, $schema_keywords, TRUE)) {
      return new \stdClass();
    }
    $map_keywords = [
      'properties',
      'patternProperties',
      'definitions',
      '$defs',
      'dependencies',
      'dependentSchemas',
    ];
    if (is_array($value)) {
      if (in_array($parent_key, $map_keywords, TRUE)) {
        $object = new \stdClass();
        foreach ($value as $key => $child) {
          $object->{$key} = $this->normalizeSchemaValue($child, (string) $key);
        }
        return $object;
      }
      if (!array_is_list($value)) {
        $object = new \stdClass();
        foreach ($value as $key => $child) {
          $object->{$key} = $this->normalizeSchemaValue($child, (string) $key);
        }
        return $object;
      }
      foreach ($value as $key => $child) {
        $value[$key] = $this->normalizeSchemaValue($child, $parent_key);
      }
    }
    return $value;
  }

}
