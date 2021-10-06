<?php

namespace Drupal\checklist\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Formatter to preview a checklist.
 *
 * @FieldFormatter(
 *   id = "checklist_preview",
 *   label = @Translation("Checklist Preview"),
 *   field_types = {
 *     "checklist"
 *   },
 *   weight = 10
 * )
 *
 * @package Drupal\checklist\Plugin\Field\FieldFormatter
 */
class ChecklistPreview extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $checklist = $item->getChecklist();

      $element = [
        '#theme' => 'table',
        '#attributes' => [
          'class' => [
            'checklist',
            'checklist-preview',
            $items->getEntity()->getEntityTypeId() . '-checklist',
            $items->getEntity()->getEntityTypeId() . '-' . str_replace(':', '--', $checklist->getKey()) . '-checklist',
          ]
        ]
      ];

      foreach ($checklist->getOrderedItems() as $name => $checklist_item) {
        $checklist_item_classes = ['ci'];
        if ($checklist_item->isComplete()) {
          $checklist_item_classes[] = 'ci-complete';
        }
        if ($checklist_item->isFailed()) {
          $checklist_item_classes[] = 'ci-failed';
        }
        if ($checklist_item->isRequired()) {
          $checklist_item_classes[] = 'ci-required';
        }
        else {
          $checklist_item_classes[] = 'ci-optional';
        }
        if ($checklist_item->isApplicable()) {
          $checklist_item_classes[] = 'ci-applicable';
        }
        else {
          $checklist_item_classes[] = 'ci-inapplicable';
        }
        if ($checklist_item->isActionable() && !$checklist_item->isComplete() && !$checklist_item->isFailed()) {
          $checklist_item_classes[] = 'ci-actionable';
        }
        else {
          $checklist_item_classes[] = 'ci-inactionable';
        }

        $element['#rows'][$name] = [
          'class' => $checklist_item_classes,
          'data' => [
            'name' => [
              'data' => [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#value' => $checklist_item->getName(),
                '#attributes' => [
                  'class' => [
                    'ci-name',
                  ],
                ],
              ],
            ],
            'label' => [
              'data' => [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#value' => $checklist_item->title->value,
                '#attributes' => [
                  'class' => [
                    'ci-label',
                  ],
                ],
              ],
            ],
          ],
        ];
      }

      $elements[$delta] = [
        'checklist' => $element
      ];
    }

    return $elements;
  }

}
