<?php

namespace Drupal\field_tools\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class StringTagFieldFormatter
 *
 * @FieldFormatter(
 *   id = "string_tag",
 *   label = @Translation("Custom Tag"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class StringTagFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays the string in a tag.');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'tag' => 'span',
        'class' => '',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['tag'] = [
      '#type' => 'select',
      '#title' => t('Tag'),
      '#options' => [
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'span' => 'span',
        'strong' => 'strong',
        'em' => 'em',
        'div' => 'div',
      ],
      '#default_value' => $this->getSetting('tag'),
    ];
    $elements['class'] = [
      '#type' => 'textfield',
      '#title' => t('Classes'),
      '#default_value' => $this->getSetting('class'),
      '#description' => t('Classes to apply to the tag.'),
    ];

    return $elements;
  }

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
    $tag = $this->getSetting('tag');
    $class = $this->getSetting('class');

    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#attributes' => [
          'class' => explode(' ', $class),
        ],
        '#value' => $item->value,
      ];
    }

    return $elements;
  }
}
