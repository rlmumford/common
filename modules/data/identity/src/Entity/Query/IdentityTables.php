<?php

namespace Drupal\identity\Entity\Query;

use Drupal\Core\Entity\Query\Sql\Tables;
use Drupal\Core\Entity\Sql\TableMappingInterface;

class IdentityTables extends Tables {

  /**
   * @var string[]
   */
  protected $identityDataTables = [];

  /**
   * @param string $field
   * @param string $type
   * @param string $langcode
   *
   * @return string
   * @throws \Drupal\Core\Entity\Query\QueryException
   */
  public function addField($field, $type, $langcode) {
    if (strpos($field, '::')) {
      list($identity_data, $field) = explode('::', $field, 2);

      // Ensure the identity data table is present.
      if (empty($this->identityDataTables[$identity_data])) {
        if (strpos($identity_data, '.')) {
          list($class, $data_type) = explode('.', $identity_data, 2);
          $condition = '%alias.identity = base_table.id AND %alias.class = :class__'.$class;
          $condition .= ' AND %alias.type = :type';
          $args = [
            ':class__'.$class => $class,
            ':type' => $data_type,
          ];
        }
        else {
          $condition = '%alias.identity = base_table.id AND %alias.class = :class__'.$identity_data;
          $args = [
            ':class__'.$identity_data => $identity_data,
          ];
        }

        $this->identityDataTables[$identity_data] = $this->sqlQuery->addJoin(
          $type,
          'identity_data',
          NULL,
          $condition,
          $args
        );
      }

      $base_table = $this->identityDataTables[$identity_data];
      $entity_type_id = 'identity_data';
      $all_revisions = $this->sqlQuery->getMetaData('all_revisions');
      $index_prefix = $identity_data;
      $specifiers = explode('.', $field);
      $count = count($specifiers) - 1;
      // This will contain the definitions of the last specifier seen by the
      // system.
      $propertyDefinitions = [];
      $entity_type = $this->entityTypeManager->getActiveDefinition($entity_type_id);

      // Everything from here onwards copied from parent::addField.
      $field_storage_definitions = $this->entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
      for ($key = 0; $key <= $count; $key++) {
        // This can either be the name of an entity base field or a configurable
        // field.
        $specifier = $specifiers[$key];
        if (isset($field_storage_definitions[$specifier])) {
          $field_storage = $field_storage_definitions[$specifier];
          $column = $field_storage->getMainPropertyName();
        }
        else {
          $field_storage = FALSE;
          $column = NULL;
        }

        // If there is revision support, only the current revisions are being
        // queried, and the field is revisionable then use the revision id.
        // Otherwise, the entity id will do.
        if (($revision_key = $entity_type->getKey('revision')) && $all_revisions && $field_storage && $field_storage->isRevisionable()) {
          // This contains the relevant SQL field to be used when joining entity
          // tables.
          $entity_id_field = $revision_key;
          // This contains the relevant SQL field to be used when joining field
          // tables.
          $field_id_field = 'revision_id';
        }
        else {
          $entity_id_field = $entity_type->getKey('id');
          $field_id_field = 'entity_id';
        }

        /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
        $table_mapping = $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping();

        // Check whether this field is stored in a dedicated table.
        if ($field_storage && $table_mapping->requiresDedicatedTableStorage($field_storage)) {
          $delta = NULL;

          if ($key < $count) {
            $next = $specifiers[$key + 1];
            // If this is a numeric specifier we're adding a condition on the
            // specific delta.
            if (is_numeric($next)) {
              $delta = $next;
              $index_prefix .= ".$delta";
              // Do not process it again.
              $key++;
              $next = $specifiers[$key + 1];
            }
            // If this specifier is the reserved keyword "%delta" we're adding a
            // condition on a delta range.
            elseif ($next == TableMappingInterface::DELTA) {
              $index_prefix .= TableMappingInterface::DELTA;
              // Do not process it again.
              $key++;
              // If there are more specifiers to work with then continue
              // processing. If this is the last specifier then use the reserved
              // keyword as a column name.
              if ($key < $count) {
                $next = $specifiers[$key + 1];
              }
              else {
                $column = TableMappingInterface::DELTA;
              }
            }
            // Is this a field column?
            $columns = $field_storage->getColumns();
            if (isset($columns[$next]) || in_array($next, $table_mapping->getReservedColumns())) {
              // Use it.
              $column = $next;
              // Do not process it again.
              $key++;
            }
            // If there are more specifiers, the next one must be a
            // relationship. Either the field name followed by a relationship
            // specifier, for example $node->field_image->entity. Or a field
            // column followed by a relationship specifier, for example
            // $node->field_image->fid->entity. In both cases, prepare the
            // property definitions for the relationship. In the first case,
            // also use the property definitions for column.
            if ($key < $count) {
              $relationship_specifier = $specifiers[$key + 1];
              $propertyDefinitions = $field_storage->getPropertyDefinitions();

              // Prepare the next index prefix.
              $next_index_prefix = "$relationship_specifier.$column";
            }
          }
          $table = $this->ensureFieldTable($index_prefix, $field_storage, $type, $langcode, $base_table, $entity_id_field, $field_id_field, $delta);
          $sql_column = $table_mapping->getFieldColumnName($field_storage, $column);
        }
        // The field is stored in a shared table.
        else {
          // ensureEntityTable() decides whether an entity property will be
          // queried from the data table or the base table based on where it
          // finds the property first. The data table is preferred, which is why
          // it gets added before the base table.
          $entity_tables = [];
          $revision_table = NULL;
          if ($all_revisions && $field_storage && $field_storage->isRevisionable()) {
            $data_table = $entity_type->getRevisionDataTable();
            $entity_base_table = $entity_type->getRevisionTable();
          }
          else {
            $data_table = $entity_type->getDataTable();
            $entity_base_table = $entity_type->getBaseTable();

            if ($field_storage && $field_storage->isRevisionable() && in_array($field_storage->getName(), $entity_type->getRevisionMetadataKeys())) {
              $revision_table = $entity_type->getRevisionTable();
            }
          }
          if ($data_table) {
            $this->sqlQuery->addMetaData('simple_query', FALSE);
            $entity_tables[$data_table] = $this->getTableMapping($data_table, $entity_type_id);
          }
          if ($revision_table) {
            $entity_tables[$revision_table] = $this->getTableMapping($revision_table, $entity_type_id);
          }
          $entity_tables[$entity_base_table] = $this->getTableMapping($entity_base_table, $entity_type_id);
          $sql_column = $specifier;

          // If there are more specifiers, get the right sql column name if the
          // next one is a column of this field.
          if ($key < $count) {
            $next = $specifiers[$key + 1];
            // If this specifier is the reserved keyword "%delta" we're adding a
            // condition on a delta range.
            if ($next == TableMappingInterface::DELTA) {
              $key++;
              if ($key < $count) {
                $next = $specifiers[$key + 1];
              }
              else {
                return 0;
              }
            }
            // If this is a numeric specifier we're adding a condition on the
            // specific delta. Since we know that this is a single value base
            // field no other value than 0 makes sense.
            if (is_numeric($next)) {
              if ($next > 0) {
                $this->sqlQuery->alwaysFalse();
              }
              $key++;
              $next = $specifiers[$key + 1];
            }
            // Is this a field column?
            $columns = $field_storage->getColumns();
            if (isset($columns[$next]) || in_array($next, $table_mapping->getReservedColumns())) {
              // Use it.
              $sql_column = $table_mapping->getFieldColumnName($field_storage, $next);
              // Do not process it again.
              $key++;
            }
          }

          $table = $this->ensureEntityTable($index_prefix, $sql_column, $type, $langcode, $base_table, $entity_id_field, $entity_tables);
        }

        // If there is a field storage (some specifiers are not) and a field
        // column, check for case sensitivity.
        if ($field_storage && $column) {
          $property_definitions = $field_storage->getPropertyDefinitions();
          if (isset($property_definitions[$column])) {
            $this->caseSensitiveFields[$field] = $property_definitions[$column]->getSetting('case_sensitive');
          }
        }

        // If there are more specifiers to come, it's a relationship.
        if ($field_storage && $key < $count) {
          // Computed fields have prepared their property definition already, do
          // it for properties as well.
          if (!$propertyDefinitions) {
            $propertyDefinitions = $field_storage->getPropertyDefinitions();
            $relationship_specifier = $specifiers[$key + 1];
            $next_index_prefix = $relationship_specifier;
          }
          $entity_type_id = NULL;
          // Relationship specifier can also contain the entity type ID, i.e.
          // entity:node, entity:user or entity:taxonomy.
          if (strpos($relationship_specifier, ':') !== FALSE) {
            list($relationship_specifier, $entity_type_id) = explode(':', $relationship_specifier, 2);
          }
          // Check for a valid relationship.
          if (isset($propertyDefinitions[$relationship_specifier]) && $propertyDefinitions[$relationship_specifier] instanceof DataReferenceDefinitionInterface) {
            // If it is, use the entity type if specified already, otherwise use
            // the definition.
            $target_definition = $propertyDefinitions[$relationship_specifier]->getTargetDefinition();
            if (!$entity_type_id && $target_definition instanceof EntityDataDefinitionInterface) {
              $entity_type_id = $target_definition->getEntityTypeId();
            }
            $entity_type = $this->entityTypeManager->getActiveDefinition($entity_type_id);
            $field_storage_definitions = $this->entityFieldManager->getActiveFieldStorageDefinitions($entity_type_id);
            // Add the new entity base table using the table and sql column.
            $base_table = $this->addNextBaseTable($entity_type, $table, $sql_column, $field_storage);
            $propertyDefinitions = [];
            $key++;
            $index_prefix .= "$next_index_prefix.";
          }
          else {
            throw new QueryException("Invalid specifier '$relationship_specifier'");
          }
        }
      }
      return "$table.$sql_column";

    }
    else {
      return parent::addField($field, $type, $langcode);
    }
  }

}
