<?php

namespace Drupal\job_role\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\job_role\Plugin\Field\FieldType\SalaryItem;

/**
 * Class SalaryDefault
 *
 * @FieldFormatter(
 *   id = "job_role_salary_default",
 *   label = @Translation("Salary default"),
 *   field_types = {
 *     "job_role_salary"
 *   }
 * )
 *
 * @package Drupal\job_role\Plugin\Field\FieldFormatter
 */
class SalaryDefault extends FormatterBase {

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

    $periods = [
      SalaryItem::TYPE_PA => $this->t('per Annum'),
      SalaryItem::TYPE_PW => $this->t('per Week'),
      SalaryItem::TYPE_PD => $this->t('per Day'),
      SalaryItem::TYPE_PH => $this->t('per Hour'),
    ];
    foreach ($items as $delta => $item) {
      $period_formatted = $periods[$item->type ?: SalaryItem::TYPE_PA];
      $min_formatted = $item->min.$item->currency_code;
      $max_formatted = $item->max.$item->currency_code;

      if (\Drupal::moduleHandler()->moduleExists('commerce_price')) {
        /** @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter */
        $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
        $min_formatted = $currency_formatter->format($item->min ?: '0', $item->currency_code);
        $max_formatted = $currency_formatter->format($item->max ?: '0', $item->currency_code);
      }

      if (empty($item->min) || $item->min == '0') {
        $elements[$delta] = [
          '#type' => 'inline_template',
          '#template' => 'Up to {{ max }} {{ period }}',
          '#context' => [
            'max' => $max_formatted,
            'period' => $period_formatted,
          ],
        ];
      }
      else if (empty($item->max) || $item->max == '0') {
        $elements[$delta] = [
          '#type' => 'inline_template',
          '#template' => 'More than {{ min }} {{ period }}',
          '#context' => [
            'min' => $min_formatted,
            'period' => $period_formatted,
          ],
        ];
      }
      else if ($item->min != $item->max) {
        // The text value has no text format assigned to it, so the user input
        // should equal the output, including newlines.
        $elements[$delta] = [
          '#type' => 'inline_template',
          '#template' => 'Between {{ min }} and {{ max }} {{ period }}',
          '#context' => [
            'min' => $min_formatted,
            'max' => $max_formatted,
            'period' => $period_formatted,
          ],
        ];
      }
      else {
        $elements[$delta] = [
          '#type' => 'inline_template',
          '#template' => '{{ value }} {{ period }}',
          '#context' => [
            'value' => $min_formatted,
            'period' => $period_formatted,
          ],
        ];
      }
    }

    return $elements;
  }
}
