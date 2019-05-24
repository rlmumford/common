<?php

namespace Drupal\relationships\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class InlineRelationshipForm
 *
 * @FieldWidget(
 *   id = "inline_relationship_form",
 *   label = @Translation("Inline relationship form"),
 *   field_types = {
 *     "relationship"
 *   },
 *   multiple_values = true
 * )
 *
 * @package Drupal\relationships\Field\FieldWidget
 */
class InlineRelationshipForm extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetManager;

  /**
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('plugin.manager.field.widget'),
      $container->get('entity_display.repository')
    );
  }

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    WidgetPluginManager $widget_manager,
    EntityDisplayRepositoryInterface $entity_display_repository
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->widgetManager = $widget_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings['add_new_end_form_mode'] = 'default';
    $settings['add_relationship_form_mode'] = 'default';
    $settings['edit_relationship_form_mode'] = 'default';

    return $settings;
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    /** @var \Drupal\relationships\Entity\RelationshipType $relationship_type */
    $relationship_type = $this->entityTypeManager->getStorage('relationship_type')->load(
      $this->getFieldSetting('relationship_type')
    );
    $relationship_end = $this->getFieldSetting('relationship_end');
    $target_end = $relationship_end == 'head' ? 'tail' : 'head';

    $form_mode_options = $this->entityDisplayRepository->getFormModeOptions($relationship_type->getEndEntityTypeId($target_end));
    $element['add_new_end_form_mode'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup(
        'Form Mode for adding a new @target_end',
        [
          '@target_end' => $relationship_type->getEndLabel($target_end),
        ]
      ),
      '#options' => $form_mode_options,
      '#default_value' => $this->getSetting('add_new_end_form_mode'),
    ];

    $element['add_relationship_form_mode'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Form Mode for adding new relationships'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions('relationship'),
      '#default_value' => $this->getSetting('add_relationship_form_mode'),
    ];

    $element['edit_relationship_form_mode'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Form Mode for editing relationships'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions('relationship'),
      '#default_value' => $this->getSetting('edit_relationship_form_mode'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\relationships\Entity\RelationshipType $relationship_type */
    $relationship_type = $this->entityTypeManager->getStorage('relationship_type')->load(
      $items->getFieldDefinition()->getSetting('relationship_type')
    );
    $relationship_end = $items->getFieldDefinition()->getSetting('relationship_end');
    $target_end = $relationship_end == 'head' ? 'tail' : 'head';

    // If this entity cannot be at one end of the relationship then we don't show anything.
    $relationship_end_handler = $relationship_type->getEndHandlerPlugin($relationship_end);
    if ($items->getEntity()->isNew()) {
      $new_entities = [$items->getEntity()];

      if ($relationship_end_handler instanceof SelectionWithAutocreateInterface) {
        $valid_new_entities = $relationship_end_handler->validateReferenceableNewEntities($new_entities);
        $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
      }
      else {
        // If the selection handler does not support referencing newly created
        // entities, all of them should be invalidated.
        $invalid_new_entities = $new_entities;
      }

      if ($invalid_new_entities) {
        return [];
      }
    }
    else {
      $target_ids = [$items->getEntity()->id()];
      $valid_target_ids = $relationship_end_handler->validateReferenceableEntities($target_ids);
      if ($invalid_target_ids = array_diff($target_ids, $valid_target_ids)) {
        return [];
      }
    }

    $parents = array_merge($element['#field_parents'] ?: [], [$items->getName()]);
    $wrapper = implode('-', $parents).'--wrapper';

    $ajax_defaults = [
      'wrapper' => $wrapper,
      'callback' => [static::class, 'formAjaxReloadWidget'],
    ];

    $widget_state = static::getWidgetState($element['#field_parents'], $items->getName(), $form_state);
    if (!isset($widget_state['entities'])) {
      $widget_state['entities'] = [];
      foreach ($items as $delta => $relationship_item) {
        $widget_state['entities'][] = [
          'form' => NULL,
          'relationship' => $relationship_item->relationship,
        ];
      }
    }
    if (!isset($widget_state['add_step'])) {
      $widget_state['add_step'] = 'select_end';
    }
    static::setWidgetState($element['#field_parents'], $items->getName(), $form_state, $widget_state);

    $element = [
      '#type' => $this->getSetting('collapsible') ? 'details' : 'fieldset',
      '#tree' => TRUE,
      '#description' => $this->fieldDefinition->getDescription(),
      '#prefix' => '<div id="' . $wrapper . '">',
      '#suffix' => '</div>',
      '#field_name' => $items->getName(),
      '#widget_root' => TRUE,
    ] + $element;
    if ($element['#type'] == 'details') {
      $element['#open'] = !$this->getSetting('collapsed');
    }

    $header = [
      $relationship_type->getEndLabel($target_end),
    ];
    $this->moduleHandler->alter('relationships_inline_relationship_table_header',$header,$this->fieldDefinition);
    $header[] = [
      'colspan' => 2,
      'data' => new TranslatableMarkup('Operations'),
    ];

    // Calculate the number of cols.
    $num_cols = 0;
    foreach ($header as $header_col) {
      if (!is_array($header_col) || empty($header_col['colspan'])) {
        $num_cols++;
      }
      else {
        $num_cols += $header_col['colspan'];
      }
    }

    $element['entities'] = [
      '#type' => 'table',
      '#header' => $header,
    ];
    foreach ($widget_state['entities'] as $delta => $relationship) {
      /** @var \Drupal\relationships\Entity\Relationship $relationship_entity */
      $relationship_entity = $relationship['relationship'];

      if (!empty($relationship_entity->_needs_delete)) {
        continue;
      }

      $element['entities'][$delta] = [];
      $row = &$element['entities'][$delta];
      $row['target'] = [
        '#markup' => !$relationship_entity->{$target_end}->isEmpty() ? $relationship_entity->{$target_end}->entity->label() : '',
      ];

      if (empty($relationship['form'])) {
        $this->moduleHandler->alter(
          'relationship_inline_relationship_table_row',
          $row,
          $relationship_entity,
          $this->fieldDefinition
        );
        $row['edit'] = [
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Edit'),
          '#name' => implode('_', $parents) . '_' . $delta . '_edit',
          '#delta' => $delta,
          '#submit' => [
            [static::class, 'formSubmitEditOpenForm'],
          ],
          '#limit_validation_errors' => [],
          '#ajax' => $ajax_defaults,
        ];
        $row['delete'] = [
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Delete'),
          '#name' => implode('_', $parents) . '_' . $delta . '_delete',
          '#delta' => $delta,
          '#submit' => [
            [static::class, 'formSubmitDeleteOpenForm'],
          ],
          '#limit_validation_errors' => [],
          '#ajax' => $ajax_defaults,
        ];
      }
      else if ($relationship['form'] == 'edit') {
        $form_parents = array_merge($parents, ['entities', $delta, 'form']);

        $row['relationship']['form'] = [
          '#wrapper_attributes' => [
            'colspan' => $num_cols - 1,
          ],
          '#type' => 'inline_entity_form',
          '#entity_type' => 'relationship',
          '#bundle' => $relationship_entity->bundle(),
          '#langcode' => \Drupal::languageManager()->getDefaultLanguage(),
          '#default_value' => $relationship_entity,
          '#op' => 'edit',
          '#form_mode' => $this->getSetting('edit_relationship_form_mode'),
          '#save_entity' => FALSE,
          '#ief_row_delta' => $delta,
          // Used by Field API and controller methods to find the relevant
          // values in $form_state.
          '#parents' => $form_parents,
        ];
        $row['relationship']['actions'] = [
          '#type' => 'actions',
        ];
        $row['relationship']['actions']['save'] = [
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Save'),
          '#name' => implode('_', $parents) . '_' . $delta . '_edit_save',
          '#ief_submit_trigger' => TRUE,
          '#delta' => $delta,
          '#submit' => [
            ['\Drupal\inline_entity_form\ElementSubmit', 'trigger'],
            [static::class, 'formSubmitEditSaveRelationship'],
            [static::class, 'formSubmitEditCloseForm'],
          ],
          '#limit_validation_errors' => [
            $form_parents,
          ],
          '#ajax' => $ajax_defaults,
        ];
        $row['relationship']['actions']['cancel'] = [
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Cancel'),
          '#name' => implode('_', $parents) . '_' . $delta . '_edit_cancel',
          '#delta' => $delta,
          '#submit' => [
            [static::class, 'formSubmitEditCloseForm'],
          ],
          '#limit_validation_errors' => [],
          '#ajax' => $ajax_defaults,
        ];
      }
      else if ($relationship['form'] == 'delete') {
        $row['relationship']['form'] = [
          '#markup' => new TranslatableMarkup('Are you sure you want to remove this relationship?'),
        ];
        $row['relationship']['actions'] = [
          '#type' => 'actions',
        ];
        $row['relationship']['actions']['confirm'] = [
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Confirm'),
          '#name' => implode('_', $parents) . '_' . $delta . '_delete_confirm',
          '#delta' => $delta,
          '#submit' => [
            [static::class, 'formSubmitDeleteConfirm'],
            [static::class, 'formSubmitEditCloseForm'],
          ],
          '#limit_validation_errors' => [],
          '#ajax' => $ajax_defaults,
        ];
        $row['relationship']['actions']['cancel'] = [
          '#type' => 'submit',
          '#value' => new TranslatableMarkup('Cancel'),
          '#name' => implode('_', $parents) . '_' . $delta . '_delete_cancel',
          '#delta' => $delta,
          '#submit' => [
            [static::class, 'formSubmitEditCloseForm'],
          ],
          '#limit_validation_errors' => [],
          '#ajax' => $ajax_defaults,
        ];
      }
    }

    $element['add'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#parents' => array_merge($parents, ['add']),
    ];
    if ($widget_state['add_step'] == 'select_end') {
      // Make a temporary relationship entity.
      /** @var \Drupal\relationships\Entity\Relationship $dummy_relationship */
      $dummy_relationship = $this->entityTypeManager->getStorage('relationship')->create([
        'type' => $relationship_type->id(),
        $relationship_end => $items->getEntity(),
      ]);

      $widget = $this->widgetManager->getInstance([
        'field_definition' => $dummy_relationship->getFieldDefinition($target_end),
        'form_mode' => 'default',
      ]);
      $element['add']['end'] = $widget->form($dummy_relationship->{$target_end},$element['add'], $form_state);
      $element['add']['end_select'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Select @target_end', ['@target_end' => $dummy_relationship->getRelationshipType()->getEndLabel($target_end)]),
        '#name' => implode('__', array_merge($parents, ['add', 'end_select'])),
        '#submit' => [
          [static::class, 'formSubmitAddEndSelect'],
        ],
        '#validate' => [],
        '#target_end' => $target_end,
        '#limit_validation_errors' => [array_merge($parents, ['add', $target_end])],
        '#ajax' => $ajax_defaults,
      ];
      $element['add']['or_new'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Add New @target_end', ['@target_end' => $dummy_relationship->getRelationshipType()->getEndLabel($target_end)]),
        '#name' => implode('__', array_merge($parents, ['add', 'or_new'])),
        '#submit' => [
          [static::class, 'formSubmitAddOrNew'],
        ],
        '#validate' => [],
        '#limit_validateion_errors' => [],
        '#ajax' => $ajax_defaults,
        '#access' => !empty($relationship_type->getEndHandlerSettings($target_end)['auto_create']),
      ];
    }
    else if ($widget_state['add_step'] == 'new_end') {
      // Make a new end.
      if (!isset($widget_state['add']['end'])) {
        $widget_state['add']['end'] = $relationship_type
          ->getEndHandlerPlugin($target_end)
          ->createNewEntity(
            $relationship_type->getEndEntityTypeId($target_end),
            $relationship_type->getEndHandlerSetting($target_end, 'auto_create_bundle'),
            '',
            \Drupal::currentUser()->id()
          );
      }

      /** @var \Drupal\Core\Entity\FieldableEntityInterface $end_entity */
      $end_entity = $widget_state['add']['end'];
      $element['add']['end'] = [
        '#type' => 'container',
      ];
      $element['add']['end']['form'] = [
        '#type' => 'inline_entity_form',
        '#entity_type' => $end_entity->getEntityTypeId(),
        '#bundle' => $end_entity->bundle(),
        '#langcode' => \Drupal::languageManager()->getDefaultLanguage(),
        '#default_value' => $end_entity,
        '#op' => 'add',
        '#form_mode' => $this->getSetting('add_new_end_form_mode'),
        '#save_entity' => TRUE,
        // Used by Field API and controller methods to find the relevant
        // values in $form_state.
        '#parents' => array_merge($parents, ['add', 'end', 'form']),
      ];

      $element['add']['end']['actions'] = [
        '#type' => 'actions',
      ];
      $element['add']['end']['actions']['continue'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Save @target_end & Continue', ['@target_end' => $relationship_type->getEndLabel($target_end)]),
        '#ief_submit_trigger' => TRUE,
        '#submit' => [
          ['\Drupal\inline_entity_form\ElementSubmit', 'trigger'],
          [static::class, 'formSubmitAddNewEnd'],
        ],
        '#limit_validation_errors' => [
          array_merge($parents, ['add', 'end', 'form']),
        ],
        '#ajax' => $ajax_defaults,
      ];
      $element['add']['end']['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Cancel'),
        '#submit' => [
          [static::class, 'fromSubmitAddNewCancel'],
        ],
        '#limit_validation_errors' => [],
        '#ajax' => $ajax_defaults,
      ];
    }
    else if ($widget_state['add_step'] == 'relationship') {
      if (!isset($widget_state['add']['relationship'])) {
        $widget_state['add']['relationship'] = $this->entityTypeManager->getStorage('relationship')->create([
          'type' => $relationship_type->id(),
          $relationship_end => $items->getEntity(),
          $target_end => $widget_state['add']['end'] ?: $widget_state['add']['end_id'],
        ]);
      }

      $relationship_entity = $widget_state['add']['relationship'];
      $element['add']['relationship']['form'] = [
        '#type' => 'inline_entity_form',
        '#entity_type' => $relationship_entity->getEntityTypeId(),
        '#bundle' => $relationship_entity->bundle(),
        '#langcode' => \Drupal::languageManager()->getDefaultLanguage(),
        '#default_value' => $relationship_entity,
        '#op' => 'edit',
        '#form_mode' => $this->getSetting('add_relationship_form_mode'),
        '#save_entity' => FALSE,
        // Used by Field API and controller methods to find the relevant
        // values in $form_state.
        '#parents' => array_merge($parents, ['add', 'relationship']),
      ];

      $element['add']['relationship']['actions'] = [
        '#type' => 'actions',
      ];
      $element['add']['relationship']['actions']['add'] = [
        '#type' => 'submit',
        '#value' => new TranslatableMarkup('Add Relationship'),
        '#ief_submit_trigger' => TRUE,
        '#submit' => [
          ['\Drupal\inline_entity_form\ElementSubmit', 'trigger'],
          [static::class, 'formSubmitAddRelationship'],
        ],
        '#limit_validation_errors' => [
          array_merge($parents, ['add', 'end', 'form']),
        ],
        '#ajax' => $ajax_defaults,
      ];
    }

    // Write the widget state back just in case.
    static::setWidgetState($parents, $items->getName(), $form_state, $widget_state);

    return $element;
  }

  /**
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // First remove all items.
    $items->setValue([]);

    $widget_state = static::getWidgetState($form['#parents'], $items->getName(), $form_state);
    if (!empty($widget_state['entities'])) {
      foreach ($widget_state['entities'] as $entity) {
        $items->appendItem($entity['relationship']);
      }
    }
    $items->filterEmptyItems();
  }

  /**
   * Get the root element of the widget.
   *
   * @param array $element
   *
   * @return array
   *   The root element of the widget.
   */
  public static function getWidgetRoot($form,$element) {
    $array_parents = $element['#array_parents'];

    do {
      array_pop($array_parents);
      $form_section = NestedArray::getValue($form, $array_parents);
    } while (empty($form_section['#widget_root']));

    return $form_section;
  }

  /**
   * Ajax callback to reload the widget.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *   The widget to re-render.
   */
  public static function formAjaxReloadWidget($form, FormStateInterface $form_state) {
    return static::getWidgetRoot($form, $form_state->getTriggeringElement());
  }

  public static function formSubmitDeleteOpenForm($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);
    $widget_state['entities'][$triggering_element['#delta']]['form'] = 'delete';
    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);

    $form_state->setRebuild(TRUE);
  }

  public static function formSubmitDeleteConfirm($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);
    $widget_state['entities'][$triggering_element['#delta']]['relationship']->_needs_delete = TRUE;
    $widget_state['entities'][$triggering_element['#delta']]['relationship']->_needs_save = FALSE;
    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Open the row form.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function formSubmitEditOpenForm($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);
    $widget_state['entities'][$triggering_element['#delta']]['form'] = 'edit';
    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Close the row form.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function formSubmitEditCloseForm($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);
    unset($widget_state['entities'][$triggering_element['#delta']]['form']);
    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);

    $form_state->setRebuild(TRUE);
  }

  public static function formSubmitEditSaveRelationship($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);

    $parents = $triggering_element['#array_parents'];
    array_pop($parents); array_pop($parents);
    array_push($parents, 'form', '#entity');
    $relationship_entity = NestedArray::getValue($form, $parents);

    $relationship_entity->_needs_save = TRUE;
    $relationship_entity->_needs_delete = FALSE;
    $widget_state['entities'][$triggering_element['#delta']]['relationship'] = $relationship_entity;

    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Form submit for when an end is selected.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function formSubmitAddEndSelect($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);

    $parents = $triggering_element['#parents'];
    array_pop($parents); $parents[] = $triggering_element['#target_end'];
    $parents[] = 0;
    $parents[] = 'target_id';
    $value = $form_state->getValue($parents);

    $widget_state['add']['end_id'] = $value;
    $widget_state['add_step'] = 'relationship';
    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Form submit callback
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function formSubmitAddOrNew($form, FormStateInterface $form_state) {
    $widget_root = static::getWidgetRoot($form, $form_state->getTriggeringElement());
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);
    $widget_state['add_step'] = 'new_end';
    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);

    $form_state->setRebuild(TRUE);
  }

  /**
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function formSubmitAddNewEnd($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);

    $parents = $triggering_element['#array_parents'];
    array_pop($parents); array_pop($parents);
    array_push($parents, 'form', '#entity');
    $end_entity = NestedArray::getValue($form, $parents);
    $widget_state['add']['end'] = $end_entity;
    $widget_state['add_step'] = 'relationship';

    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);
    $form_state->setRebuild(TRUE);
  }

  public static function fromSubmitAddNewCancel($form, FormStateInterface $form_state) {
    $widget_root = static::getWidgetRoot($form, $form_state->getTriggeringElement());
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);

    $widget_state['add_step'] = 'select';
    unset($widget_state['add']['end']);

    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);
    $form_state->setRebuild(TRUE);
  }

  public static function formSubmitAddRelationship($form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $widget_root = static::getWidgetRoot($form, $triggering_element);
    $widget_state = static::getWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state);

    $parents = $triggering_element['#array_parents'];
    array_pop($parents); array_pop($parents);
    array_push($parents, 'form', '#entity');
    $relationship_entity = NestedArray::getValue($form, $parents);

    $widget_state['entities'][] = [
      'relationship' => $relationship_entity,
    ];
    $widget_state['add_step'] = 'select_end';
    $widget_state['add'] = [];

    static::setWidgetState($widget_root['#field_parents'], $widget_root['#field_name'], $form_state, $widget_state);
    $form_state->setRebuild(TRUE);
  }
}
