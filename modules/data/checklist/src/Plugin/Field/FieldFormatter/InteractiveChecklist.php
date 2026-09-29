<?php

namespace Drupal\checklist\Plugin\Field\FieldFormatter;

use Drupal\checklist\ChecklistActionResourceCollectorInterface;
use Drupal\checklist\ChecklistActionResourcePaneBuilder;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\ChecklistRowBuilder;
use Drupal\checklist\Form\ChecklistCompleteForm;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interactive checklist field formatter.
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
   * The checklist tempstore factory.
   *
   * @var \Drupal\checklist\ChecklistTempstoreRepository
   */
  protected $tempstoreRepo;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The action resource collector.
   *
   * @var \Drupal\checklist\ChecklistActionResourceCollectorInterface
   */
  protected ChecklistActionResourceCollectorInterface $resourceCollector;

  /**
   * Builds stable resource pane markup.
   *
   * @var \Drupal\checklist\ChecklistActionResourcePaneBuilder
   */
  protected ChecklistActionResourcePaneBuilder $resourcePaneBuilder;

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
      $container->get('form_builder'),
      $container->get('checklist.row_builder'),
      $container->get('checklist.action_resource_collector'),
      $container->get('checklist.action_resource_pane_builder')
    );
  }

  /**
   * InteractiveChecklist constructor.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The field label.
   * @param string $view_mode
   *   The view mode id.
   * @param array $third_party_settings
   *   The third party settings.
   * @param \Drupal\checklist\ChecklistTempstoreRepository $checklist_tempstore_repository
   *   The checklist tempstore repository.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\checklist\ChecklistRowBuilder $rowBuilder
   *   Builds initial and refreshed rows.
   * @param \Drupal\checklist\ChecklistActionResourceCollectorInterface $resource_collector
   *   The action resource collector.
   * @param \Drupal\checklist\ChecklistActionResourcePaneBuilder $resource_pane_builder
   *   The resource pane builder.
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
    FormBuilderInterface $form_builder,
    protected ChecklistRowBuilder $rowBuilder,
    ChecklistActionResourceCollectorInterface $resource_collector,
    ChecklistActionResourcePaneBuilder $resource_pane_builder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->tempstoreRepo = $checklist_tempstore_repository;
    $this->formBuilder = $form_builder;
    $this->classResolver = $class_resolver;
    $this->resourceCollector = $resource_collector;
    $this->resourcePaneBuilder = $resource_pane_builder;
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
      // Gates can depend on users, missing outcomes and global providers.
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'library' => [
          'checklist/interactive_checklist',
        ],
      ],
    ];

    /** @var \Drupal\checklist\Plugin\Field\FieldType\ChecklistItem $item */
    foreach ($items as $delta => $item) {
      $checklist = $item->getChecklist();
      $collected_resources = $this->resourceCollector->collect($checklist);
      $id = $checklist->getEntity()->getEntityTypeId()
        . '--' . str_replace(':', '--', $checklist->getKey());

      // Make sure the checklist is set to tempstore.
      $this->tempstoreRepo->set($checklist);

      $element = [
        '#id' => $id,
        '#type' => 'table',
        '#attributes' => [
          'class' => [
            'checklist',
            'interactive-checklist',
            $items->getEntity()->getEntityTypeId() . '-checklist',
            $items->getEntity()->getEntityTypeId() . '-' . str_replace(':', '--', $checklist->getKey()) . '-checklist',
          ],
        ],
      ];

      foreach ($checklist->getOrderedItems() as $name => $checklist_item) {
        if ($row = $this->rowBuilder->build($checklist, $checklist_item, $langcode)) {
          $element[$name] = $row;
        }
      }

      $elements[$delta] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $this->resourcePaneBuilder->getWorkspaceId($checklist),
          'class' => ['checklist-workspace'],
        ],
        'mobile_navigation' => [
          '#type' => 'container',
          '#weight' => 100,
          '#attributes' => [
            'class' => ['checklist-workspace-mobile-navigation'],
            'role' => 'navigation',
            'aria-label' => $this->t('Checklist navigation'),
            'hidden' => TRUE,
          ],
          'checklist' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => $this->t('Checklist'),
            '#attributes' => [
              'type' => 'button',
              'class' => ['checklist-workspace-mobile-tab'],
              'data-checklist-workspace-view' => 'checklist',
              'aria-pressed' => 'true',
            ],
          ],
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['checklist-workspace-actions'],
            'data-checklist-workspace-panel' => 'checklist',
          ],
          'checklist' => $element,
          'completion_form' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['checklist-complete-form'],
            ],
            'form' => $this->formBuilder->getForm(
              $this->classResolver
                ->getInstanceFromDefinition(ChecklistCompleteForm::class)
                ->setChecklist($checklist)
            ),
          ],
        ],
      ];
      $elements[$delta]['resources'] = $this->resourcePaneBuilder->build($collected_resources, $checklist);
      if ($collected_resources) {
        $elements[$delta]['#attributes']['class'][] = 'checklist-workspace--resources';
      }
    }

    return $elements;
  }

}
