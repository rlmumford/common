<?php

namespace Drupal\checklist\Plugin\Field\FieldFormatter;

use Drupal\checklist\ChecklistContextCollectorInterface;
use Drupal\checklist\ChecklistActionResourceCollectorInterface;
use Drupal\checklist\ChecklistTempstoreRepository;
use Drupal\checklist\Form\ChecklistCompleteForm;
use Drupal\checklist\Form\ChecklistItemRowForm;
use Drupal\checklist\Plugin\ChecklistItemHandler\SimplyCheckableChecklistItemHandler;
use Drupal\checklist\PluginForm\CustomFormObjectClassInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\typed_data\PlaceholderResolverTrait;
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
  use PlaceholderResolverTrait;

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
   * The context handler service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected ContextHandlerInterface $contextHandler;

  /**
   * The context collector service.
   *
   * @var \Drupal\checklist\ChecklistContextCollectorInterface
   */
  protected ChecklistContextCollectorInterface $contextCollector;

  /**
   * The action resource collector.
   *
   * @var \Drupal\checklist\ChecklistActionResourceCollectorInterface
   */
  protected ChecklistActionResourceCollectorInterface $resourceCollector;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return (new static(
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
      $container->get('context.handler'),
      $container->get('checklist.context_collector'),
      $container->get('checklist.action_resource_collector')
    ))->setPlaceholderResolver($container->get('typed_data.placeholder_resolver'));
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
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\checklist\ChecklistContextCollectorInterface $context_collector
   *   The context collector.
   * @param \Drupal\checklist\ChecklistActionResourceCollectorInterface $resource_collector
   *   The action resource collector.
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
    ContextHandlerInterface $context_handler,
    ChecklistContextCollectorInterface $context_collector,
    ChecklistActionResourceCollectorInterface $resource_collector,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->tempstoreRepo = $checklist_tempstore_repository;
    $this->formBuilder = $form_builder;
    $this->classResolver = $class_resolver;
    $this->contextHandler = $context_handler;
    $this->contextCollector = $context_collector;
    $this->resourceCollector = $resource_collector;
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

    $placeholder_datas = [
      $items->getEntity()->getEntityTypeId() => EntityAdapter::createFromEntity($items->getEntity()),
    ];
    /** @var \Drupal\checklist\Plugin\Field\FieldType\ChecklistItem $item */
    foreach ($items as $delta => $item) {
      $checklist = $item->getChecklist();
      $contexts = $this->contextCollector->collectRuntimeContexts($checklist);
      foreach ($contexts as $name => $context) {
        $placeholder_datas[$name] = $context->getContextData();
      }
      $collected_resources = $this->resourceCollector->collect($checklist);
      uasort($collected_resources, static function (array $a, array $b): int {
        return $a['resource']->getWeight() <=> $b['resource']->getWeight();
      });
      $initial_resource_key = array_key_first($collected_resources);
      $resource_keys_by_item = [];
      foreach ($collected_resources as $resource_key => $collected_resource) {
        foreach ($collected_resource['owners'] as $owner) {
          $resource_keys_by_item[$owner] = $resource_key;
        }
      }
      $pane_id = Html::getId('checklist-resource-pane-' . $items->getEntity()->uuid() . '-' . $items->getName() . '-' . $delta);

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
        $handler = $checklist_item->getHandler();

        // Handle contexts.
        if ($handler instanceof ContextAwarePluginInterface) {
          try {
            $this->contextHandler->applyContextMapping($handler, $contexts);
          }
          catch (MissingValueContextException $exception) {
            // We're ok with missing values here, do nothing.
          }
          catch (ContextException $exception) {
            // Having the context not available at all is more of a problem, so
            // we just skip this CI.
            continue;
          }
        }

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

        $form_class = ChecklistItemRowForm::class;
        if (is_subclass_of($handler->getFormClass('row'), CustomFormObjectClassInterface::class)) {
          $form_class = [$handler->getFormClass('row'), 'getFormObjectClass']($handler, $form_class);
        }

        /** @var \Drupal\checklist\Form\ChecklistItemRowForm $form_obj */
        $form_obj = $this->classResolver->getInstanceFromDefinition($form_class);
        $form_obj->setChecklistItem($checklist_item);
        $form_obj->setActionUrl(Url::fromRoute(
          'checklist.item.row_form',
          [
            'entity_type' => $checklist->getEntity()->getEntityTypeId(),
            'entity_id' => $checklist->getEntity()->id(),
            'checklist' => $checklist->getKey(),
            'item_name' => $checklist_item->getName(),
          ]
        ));

        // @todo Estimates
        // @todo Icons
        $cache_metadata = new BubbleableMetadata();
        $element[$name] = [
          '#attributes' => [
            'class' => $checklist_item_classes,
            'data-has-resource' => isset($resource_keys_by_item[$name]),
            'data-is-complete' => $checklist_item->isComplete(),
            'data-is-failed' => $checklist_item->isFailed(),
            'data-is-actionable' => $checklist_item->isActionable(),
            'data-ciid' => $checklist_item->id(),
            'data-ciname' => $checklist_item->getName(),
          ],
          'checkbox' => $this->formBuilder->getForm($form_obj),
          'label' => [
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
        ];
        if (isset($resource_keys_by_item[$name])) {
          $resource_key = $resource_keys_by_item[$name];
          $element[$name]['resource'] = [
            '#type' => 'button',
            '#value' => $this->t('Open resource'),
            '#attributes' => [
              'class' => ['checklist-resource-trigger'],
              'type' => 'button',
              'data-resource-key' => $resource_key,
              'aria-controls' => $this->getResourcePanelId($pane_id, $resource_key),
              'aria-pressed' => $resource_key === $initial_resource_key ? 'true' : 'false',
            ],
          ];
          $element[$name]['#attributes']['class'][] = 'checklist-item-has-resource';
        }
        $cache_metadata->applyTo($element[$name]);

        if ($checklist_item->getHandler() instanceof SimplyCheckableChecklistItemHandler) {
          $element[$name]['checkbox']['#attributes']['class'][] = 'checklist-checkbox-checkable';
          $element[$name]['#attributes']['class'][] = 'checklist-item-checkable';
        }
        if ($checklist_item->getHandler()->hasFormClass('action')) {
          $element[$name]['#attributes']['class'][] = 'checklist-item-has-form';

          $element[$name]['action_form'] = [
            '#wrapper_attributes' => [
              'class' => ['action-form-container'],
              'id' => $id . '--' . $name . '--action-form-container',
            ],
          ];
        }
      }

      $element['#items']['__checklist_complete'] = [
        '#wrapper_attributes' => [
          'class' => ['ci', 'ci-checklist-complete-form'],
      // @todo Add resources
          'data-has-resource' => FALSE,
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Complete'),
          '#attributes' => [
            'class' => [
              'ci-label',
            ],
          ],
        ],
        'form' => $this->formBuilder->getForm(
          $this->classResolver
            ->getInstanceFromDefinition(ChecklistCompleteForm::class)
            ->setChecklist($checklist)
        ),
      ];

      $elements[$delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['checklist-workspace']],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['checklist-workspace-actions']],
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
      if ($resource_pane = $this->buildResourcePane($collected_resources, $pane_id)) {
        $elements[$delta]['resources'] = $resource_pane;
        $elements[$delta]['#attributes']['class'][] = 'checklist-workspace--resources';
      }
    }

    return $elements;
  }

  /**
   * Builds a navigable pane for resources contributed by checklist items.
   *
   * @param array $resources
   *   Collected resources, keyed by their shared key.
   * @param string $pane_id
   *   Unique DOM ID for this checklist pane.
   *
   * @return array|null
   *   The resource pane render array, or NULL when no resources are available.
   */
  protected function buildResourcePane(array $resources, string $pane_id): ?array {
    if (!$resources) {
      return NULL;
    }

    $navigation = [];
    $panels = [];
    $first = TRUE;
    foreach ($resources as $key => $entry) {
      $resource = $entry['resource'];
      $panel_id = $this->getResourcePanelId($pane_id, $key);
      $navigation[] = [
        '#type' => 'button',
        '#value' => $resource->getLabel() ?? reset($entry['owners']),
        '#attributes' => [
          'type' => 'button',
          'class' => ['checklist-resource-select'],
          'data-resource-key' => $key,
          'aria-controls' => $panel_id,
          'aria-pressed' => $first ? 'true' : 'false',
        ],
      ];
      $panels[$key] = [
        '#type' => 'container',
        '#weight' => $resource->getWeight(),
        '#attributes' => [
          'id' => $panel_id,
          'class' => ['checklist-resource-content'],
          'data-resource-key' => $key,
          'data-resource-owners' => implode(' ', $entry['owners']),
          'data-resource-closeable' => $resource->isCloseable() ? 'true' : 'false',
          'data-resource-icon' => $resource->getIcon() ?? '',
          'data-resource-pinned' => $resource->isPinned() ? 'true' : 'false',
          'hidden' => !$first,
        ],
        'content' => $resource->getContent(),
      ];
      $first = FALSE;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $pane_id,
        'class' => ['checklist-resource-pane'],
        'role' => 'complementary',
        'aria-label' => $this->t('Checklist resources'),
      ],
      'navigation' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['checklist-resource-navigation'], 'role' => 'group'],
        'items' => $navigation,
      ],
      'panels' => $panels,
    ];
  }

  /**
   * Creates a stable, collision-resistant DOM ID for a resource panel.
   *
   * @param string $pane_id
   *   The checklist pane ID.
   * @param string $key
   *   The resource's shared key.
   *
   * @return string
   *   A sanitized panel ID.
   */
  protected function getResourcePanelId(string $pane_id, string $key): string {
    return Html::getId($pane_id . '-' . $key . '-' . substr(hash('sha256', $key), 0, 8));
  }

}
