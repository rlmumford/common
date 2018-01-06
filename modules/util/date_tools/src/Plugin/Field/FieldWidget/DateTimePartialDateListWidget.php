<?php

namespace Drupal\date_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeDatelistWidget;

/**
 * Plugin implementation of the datetime_partialdatelist.
 *
 * @FieldWidget(
 *   id = "datetime_partialdatelist",
 *   label = @Translation("Select list (partial)"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class DateTimePartialDateListWidget extends DateTimeDatelistWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'components' => array('year', 'month', 'day'),
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $components = array_filter($this->getSetting('components'));
    foreach ($element['value']['#date_part_order'] as $key => $part) {
      if (!in_array($part, $components)) {
        unset($element['value']['#date_part_order'][$key]);
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['components'] = [
      '#type' => 'checkboxes',
      '#title' => t('Components'),
      '#default_value' => $this->getSetting('components'),
      '#options' => [
        'year' => t('Year'),
        'month' => t('Month'),
        'day' => t('Day'),
        'hour' => t('Hour'),
        'minute' => t('Minute'),
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    array_unshift($summary, t('Date components: @comp', ['@comp' => implode(', ', array_filter($this->getSetting('components')))]));
    return $summary;
  }
}

