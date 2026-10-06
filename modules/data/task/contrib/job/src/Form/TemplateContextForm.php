<?php

namespace Drupal\task_job\Form;

/**
 * Edits portable input definitions on the template, independently of exposure.
 */
final class TemplateContextForm {

  /**
   * Builds editable definitions and one optional new input row.
   */
  public static function build(array $definitions, array $types): array {
    $options = [];
    foreach ($types as $id => $type) {
      $options[$id] = $type['label'];
    }
    $element = [
      '#type' => 'details',
      '#title' => t('Template contexts'),
      '#description' => t('Declare inputs used by this template’s items. Map them where the template is invoked. Normal task contexts remain available separately.'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#weight' => -2,
    ];
    foreach ($definitions + ['_new' => []] as $name => $definition) {
      $row = [
        '#type' => 'details',
        '#title' => $definition['label'] ?? t('New input'),
        '#open' => $name !== '_new',
      ];
      $row['name'] = [
        '#type' => 'textfield',
        '#title' => t('Machine name'),
        '#default_value' => $name === '_new' ? '' : $name,
        '#disabled' => $name !== '_new',
      ];
      $row['label'] = [
        '#type' => 'textfield',
        '#title' => t('Input label'),
        '#default_value' => $definition['label'] ?? '',
      ];
      $row['type'] = [
        '#type' => 'select',
        '#title' => t('Data type'),
        '#options' => $options,
        '#default_value' => $definition['type'] ?? 'string',
      ];
      $row['description'] = [
        '#type' => 'textfield',
        '#title' => t('Description'),
        '#default_value' => $definition['description'] ?? '',
      ];
      $row['required'] = [
        '#type' => 'checkbox',
        '#title' => t('Required'),
        '#default_value' => $definition['required'] ?? TRUE,
      ];
      $row['multiple'] = [
        '#type' => 'checkbox',
        '#title' => t('Allow multiple values'),
        '#default_value' => $definition['multiple'] ?? FALSE,
      ];
      if ($name !== '_new') {
        $row['remove'] = ['#type' => 'checkbox', '#title' => t('Remove input')];
      }
      $element[$name] = $row;
    }
    return $element;
  }

  /**
   * Extracts definitions after form validation; blank new rows are ignored.
   */
  public static function configuration(array $values): array {
    $definitions = [];
    foreach ($values as $key => $row) {
      if (!is_array($row) || !empty($row['remove'])) {
        continue;
      }
      $name = $key === '_new' ? trim($row['name'] ?? '') : $key;
      if ($name !== '') {
        $definitions[$name] = [
          'type' => $row['type'],
          'label' => trim($row['label']),
          'description' => $row['description'] ?? '',
          'required' => !empty($row['required']),
          'multiple' => !empty($row['multiple']),
        ];
      }
    }
    return $definitions;
  }

}
