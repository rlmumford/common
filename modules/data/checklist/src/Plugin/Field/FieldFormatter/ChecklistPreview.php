<?php

namespace Drupal\checklist\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\typed_data\PlaceholderResolverTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  use PlaceholderResolverTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    )->setPlaceholderResolver($container->get('typed_data.placeholder_resolver'));
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $placeholder_datas = [
      $items->getEntity()->getEntityTypeId() => EntityAdapter::createFromEntity($items->getEntity()),
    ];
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
          ],
        ],
      ];

      foreach ($checklist->getOrderedItems() as $name => $checklist_item) {
        $placeholder_datas['checklist_item'] = EntityAdapter::createFromEntity($checklist_item);

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

        $cache_metadata = new BubbleableMetadata();
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
                '#value' => $this->getPlaceholderResolver()->replacePlaceHolders(
                  $checklist_item->title->value,
                  $placeholder_datas,
                  $cache_metadata,
                  ['langcode' => $langcode]
                ),
                '#attributes' => [
                  'class' => [
                    'ci-label',
                  ],
                ],
              ],
            ],
          ],
        ];
        $cache_metadata->applyTo($element['#rows'][$name]);
      }

      $elements[$delta] = [
        'checklist' => $element,
      ];
    }

    return $elements;
  }

}
