<?php

namespace Drupal\checklist\Plugin\Field\FieldFormatter;

use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Form\ChecklistCompleteForm;
use Drupal\checklist\Form\ChecklistRowForm;
use Drupal\checklist\Plugin\ChecklistItemHandler\SimplyCheckableChecklistItemHandler;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
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
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

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
      $container->get('checklist.tempstore_repository'),
      $container->get('class_resolver'),
      $container->get('form_builder')
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
    ChecklistTempstoreRepository $checklist_tempstore_repository,
    ClassResolverInterface $class_resolver,
    FormBuilderInterface $form_builder
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->tempstoreRepo = $checklist_tempstore_repository;
    $this->formBuilder = $form_builder;
    $this->classResolver = $class_resolver;
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
      ],
    ];

    /** @var \Drupal\checklist\Plugin\Field\FieldType\ChecklistItem $item */
    foreach ($items as $delta => $item) {
      $checklist = $item->getChecklist();
      $id = $checklist->getEntity()->getEntityTypeId()
        .'--'.str_replace(':', '--', $checklist->getKey());

      // Make sure the checklist is set to tempstore.
      $this->tempstoreRepo->set($checklist);

      $element = [
        '#id' => $id,
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#attributes' => [
          'class' => [
            'checklist',
            'interactive-checklist',
            $items->getEntity()->getEntityTypeId().'-checklist',
            $items->getEntity()->getEntityTypeId().'-'.str_replace(':', '--', $checklist->getKey()).'-checklist',
          ],
        ],
        '#items' => [],
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

        /** @var \Drupal\checklist\Form\ChecklistRowForm $form_obj */
        $form_obj = $this->classResolver->getInstanceFromDefinition(ChecklistRowForm::class);
        $form_obj->setChecklistItem($checklist_item);

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
          'checkbox' => $this->formBuilder->getForm($form_obj),
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
            '#value' => $checklist_item->title->value,
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

      $element['#items']['__checklist_complete'] = [
        '#wrapper_attributes' => [
          'class' => ['ci', 'ci-checklist-complete-form'],
          'data-has-resource' => FALSE, // @todo: Add resources
        ],
        'name' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => '',
          '#attributes' => [
            'class' => [
              'ci-name',
            ],
          ],
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Complete'),
          '#attributes' => [
            'class' => [
              'ci-label',
            ]
          ]
        ],
        'form' => $this->formBuilder->getForm(
          $this->classResolver
            ->getInstanceFromDefinition(ChecklistCompleteForm::class)
            ->setChecklist($checklist)
        )
      ];

      $elements[$delta] = [
        'checklist' => $element,
        'action_form' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'id' => $id.'--action-form-container',
          ],
        ],
      ];
    }

    return $elements;
  }
}
