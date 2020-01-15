<?php

use \Drupal\field\Entity\FieldStorageConfig;

/**
 * Implements hook_views_data_alter().
 */
function date_tools_views_data_alter(&$data) {
  // Loops through fields definitions looking for date and datetime fields
  // and change the standard date handler with our own.
  foreach ($data as $table_name => &$table) {
    // Skip files because they have very specific fields.
    if (isset($table['table']['provider']) && $table['table']['provider'] === 'file') {
      continue;
    }

    foreach ($table as $id => &$field) {
      // Check to see if the field being used here is a date or datetime field.
      if (!empty($field['field']['entity_type']) && !empty($field['field']['field_name'])) {
        $field_storage = FieldStorageConfig::loadByName($field['field']['entity_type'], $field['field']['field_name']);
        if ($field_storage && in_array($field_storage->getType(), ['date', 'datetime'])) {
          $field['field']['id'] = 'date_tools_format_sql_date';
          $field['field']['type'] = $field_storage->getType();
        }
      }
      elseif (!empty($field['field']['id']) && !empty($field['argument']['id'])) {
        if ($field['field']['id'] === 'field' && in_array($field['argument']['id'], ['date', 'datetime'])) {
          $field['field']['id'] = 'date_tools_format_sql_date';
          $field['field']['type'] = $field['argument']['id'];
        }
      }
    }
  }
}
