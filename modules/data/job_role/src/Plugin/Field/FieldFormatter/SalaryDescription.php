<?php

namespace Drupal\job_role\Plugin\Field\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Class SalaryDescription
 *
 * @FieldFormatter(
 *   id = "job_role_salary_description",
 *   label = @Translation("Salary description"),
 *   field_types = {
 *     "job_role_salary"
 *   }
 * )
 *
 * @package Drupal\job_role\Plugin\Field\FieldFormatter
 */
class SalaryDescription extends FormatterBase {

  /**
   * Builds a renderable array for a field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      // The text value has no text format assigned to it, so the user input
      // should equal the output, including newlines.
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ value|nl2br }}',
        '#context' => ['value' => $item->desc],
      ];
    }

    return $elements;
  }
}
