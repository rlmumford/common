<?php

namespace Drupal\checklist\Plugin\Field\FieldFormatter;

use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Plugin\ChecklistItemHandler\SimplyCheckableChecklistItemHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InteractiveChecklist
 *
 * @FieldFormatter(
 *   id = "checklist_interactive",
 *   label = @Translation("Interactive Checklist"),
 *   field_types = {
 *     "checklist"
 *   },
 *   weight = 10
 * )
 *
 * @package Drupal\checklist\Plugin\Field\FieldFormatter
 */
class InteractiveChecklist extends FormatterBase {

  /**
   * We're going to use the shared tempstore!
   *
   * @var \Drupal\checklist\ChecklistTempstoreRepository
   */
  protected $tempstoreRepo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('checklist.tempstore_repository')
    );
  }

  /**
   * InteractiveChecklist constructor.
   *
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   * @param array $settings
   * @param string $label
   * @param string $view_mode
   * @param array $third_party_settings
   * @param \Drupal\checklist\ChecklistTempstoreRepository $checklist_tempstore_repository
   */
  public function __construct(
    string $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    ChecklistTempstoreRepository $checklist_tempstore_repository
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->tempstoreRepo = $checklist_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_container_selector' => '',
      'resource_container_selector' => '',
    ] + parent::defaultSettings();
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
    $elements = [
      '#attached' => [
        'library' => [
          'checklist/interactive_checklist'
        ],
        'settings' => [
          'form-container-selector' => $this->getSetting('form_container_selector'),
          'resource-container-selector' => $this->getSetting('resource_container_selector'),
        ],
      ],
    ];

    /** @var \Drupal\checklist\Plugin\Field\FieldType\ChecklistItem $item */
    foreach ($items as $delta => $item) {
      $checklist = $item->getChecklist();

      // Make sure the checklist is set to tempstore.
      $this->tempstoreRepo->set($checklist);

      $element = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#attributes' => [
          'class' => [
            'checklist',
            'interactive-checklist',
            $items->getEntity()->getEntityTypeId().'-checklist',
          ],
        ],
        '#items' => [],
      ];

      foreach ($checklist->getOrderedItems() as $name => $checklist_item) {
        $checklist_item_classes = [];
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
        if ($checklist_item->isActionable()) {
          $checklist_item_classes[] = 'ci-actionable';
        }
        else {
          $checklist_item_classes[] = 'ci-inactionable';
        }

        // @todo: Estimates
        // @todo: Icons
        $element['#items'][$name] = [
          '#wrapper_attributes' => [
            'class' => $checklist_item_classes,
            'data-has-resource' => FALSE, // @todo: Add resources
            'data-is-complete' => $checklist_item->isComplete(),
            'data-is-failed' => $checklist_item->isFailed(),
            'data-is-actionable' => $checklist_item->isActionable(),
            'data-ciid' => $checklist_item->id(),
            'data-ciname' => $checklist_item->getName(),
          ],
          'checkbox' => [
            '#theme' => 'input__checkbox',
            '#attributes' => [
              'class' => [
                'checklist-checkbox',
                $items->getEntity()->getEntityTypeId().'-checklist-checkbox',
              ],
              'type' => 'checkbox',
              'checked' => $checklist_item->isComplete(),
              'disabled' => $checklist_item->isComplete() || $checklist_item->isFailed(),
              'value' => 1,
            ],
          ],
          'name' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $checklist_item->getName(),
            '#attributes' => [
              'class' => [
                'ci-name',
              ],
            ],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $checklist_item->label(),
            '#attributes' => [
              'class' => [
                'ci-label',
              ]
            ]
          ]
        ];

        if ($checklist_item->getHandler() instanceof  SimplyCheckableChecklistItemHandler) {
          $element['#items'][$name]['checkbox']['#attributes']['class'][] = 'checklist-checkbox-checkable';
          $element['#items'][$name]['#wrapper_attributes']['class'][] = 'checklist-item-checkable';
        }
        if ($checklist_item->getHandler()->hasFormClass('action')) {
          $element['#items'][$name]['#wrapper_attributes']['class'][] = 'checklist-item-has-form';
        }
        // @todo: When resources are introduce, uncomment the below
        // if ($checklist_item->getHandler()->hasResource()) {
        //   $element['#items'][$name]['#wrapper_attributes']['class'][] = 'checklist-item-has-resource';
        // }
      }

      $elements[$delta] = $element;
    }

    return $elements;
  }
}
