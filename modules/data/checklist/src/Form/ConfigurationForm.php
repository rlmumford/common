<?php

namespace Drupal\checklist\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Rebuild controls shared by nested checklist configuration forms.
 */
class ConfigurationForm {

  /**
   * Reads posted controls using the containing form's absolute input path.
   */
  public static function input(array $element, FormStateInterface $state): array {
    $input = $state->getUserInput() ?? [];
    $input = NestedArray::getValue($input, $element['#parents']);
    return is_array($input) ? $input : [];
  }

  /**
   * Adds a rebuild-only button, optionally growing a repeated section.
   */
  public static function button(array $parents, $label, bool $add = FALSE): array {
    return [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => implode('_', $parents) . ($add ? '_add' : '_update'),
      '#limit_validation_errors' => [],
      '#checklist_configuration_add' => $add ? hash('sha256', serialize($parents)) : NULL,
      '#submit' => [[static::class, 'rebuild']],
    ];
  }

  /**
   * Retains input without running the containing form's persistence handlers.
   */
  public static function rebuild(array &$form, FormStateInterface $state): void {
    if ($key = $state->getTriggeringElement()['#checklist_configuration_add']) {
      $state->set($key, ($state->get($key) ?? 0) + 1);
    }
    $state->setRebuild();
  }

  /**
   * Supplies stored rows plus requested blank rows, scoped to the subform.
   */
  public static function rows(array $configuration, array $parents, FormStateInterface $state): array {
    $rows = [];
    foreach ($configuration as $name => $value) {
      $rows[] = ['name' => $name, 'configuration' => $value];
    }
    $extra = $state->get(hash('sha256', serialize($parents))) ?? 0;
    return array_merge($rows, array_fill(0, $extra + 1, ['name' => '', 'configuration' => []]));
  }

}
