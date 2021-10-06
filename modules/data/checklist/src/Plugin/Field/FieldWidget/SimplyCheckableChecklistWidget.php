<?php

namespace Drupal\checklist\Plugin\Field\FieldWidget;

use Drupal\checklist\Plugin\ChecklistItemHandler\SimplyCheckableChecklistItemHandler;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget to edit a simple checklist.
 *
 * This widget only allows the addition or alteration simply checkable checklist
 * items.
 *
 * @FieldWidget(
 *   id = "checklist_simple",
 *   label = @Translation("Simple Checklist"),
 *   field_types = {
 *     "checklist"
 *   }
 * )
 *
 * @package Drupal\checklist\Plugin\Field\FieldWidget
 */
class SimplyCheckableChecklistWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    /** @var \Drupal\checklist\Plugin\Field\FieldType\ChecklistItem $item */
    $item = $items->get($delta);

    if (!$item->id) {
      // @todo Allow the selection of a checklist type plugin.
      return $element;
    }

    $widget_state = static::getWidgetState($form['#parents'], $items->getFieldDefinition()->getName(), $form_state);
    if (!isset($widget_state[$delta]['checklist_items'])) {
      $widget_state[$delta]['checklist_items'] = $item->getChecklist()->getOrderedItems();
      static::setWidgetState($form['#parents'], $items->getFieldDefinition()->getName(), $form_state, $widget_state);
    }

    $element += [
      '#type' => 'fieldset',
      'table' => [
        '#type' => 'table',
        '#parents' => array_merge($element['#field_parents'] ?? [], [$items->getFieldDefinition()->getName(), $delta]),
        '#header' => [
          $this->t('Name'),
          $this->t('Title'),
          '',
        ],
      ],
    ];
    foreach ($widget_state[$delta]['checklist_items'] as $name => $checklist_item) {
      // Only allow editing checklist items that have not already been saved as
      // these may have already been acted upon.
      // @todo in future relax this restriction to allow editing 'incomplete'
      // but saved checklist items.
      if ($checklist_item->isNew() && $checklist_item->getHandler() instanceof SimplyCheckableChecklistItemHandler) {
        $element['table'][$name]['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Name'),
          '#title_display' => 'invisible',
          '#length' => 6,
          '#default_value' => $name,
        ];
        $element['table'][$name]['title'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Title'),
          '#title_display' => 'invisible',
          '#default_value' => $checklist_item->title->value,
        ];
        $element['table'][$name]['operations'] = [
          'remove' => [
            '#type' => 'submit',
            '#name' => implode('--', array_merge($form['#parents'], [$items->getFieldDefinition()->getName(), $delta, $name, 'remove'])),
            '#value' => $this->t('Remove'),
            '#limit_validation_errors' => [],
            '#submit' => [
              static::class . '::removeChecklistItemFormSubmit',
            ],
          ],
        ];
      }
      else {
        $element['table'][$name]['name'] = [
          '#markup' => $name,
        ];
        $element['table'][$name]['title'] = [
          '#markup' => $checklist_item->title->value,
        ];
      }
    }

    $element['table']['__new_item']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#title_display' => 'invisible',
      '#length' => 6,
    ];
    $element['table']['__new_item']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#title_display' => 'invisible',
    ];
    $element['table']['__new_item']['operations'] = [
      'add' => [
        '#type' => 'submit',
        '#checklist_type' => $item->id,
        '#name' => implode('--', array_merge($element['#field_parents'], [$items->getFieldDefinition()->getName(), $delta, 'add'])),
        '#value' => $this->t('Add Item'),
        '#limit_validation_errors' => [
          array_merge($element['table']['#parents'], ['__new_item']),
        ],
        '#submit' => [
          static::class . '::addChecklistItemFormSubmit',
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $path = array_merge($form['#parents'], [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    $widget_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    foreach ($values as $delta => $checklist_values) {
      /** @var \Drupal\checklist\Plugin\Field\FieldType\ChecklistItem $item */
      $item = $items->get($delta);

      $config = $item->configuration;
      $config['default_items'] = $config['default_items'] ?? [];
      $existing_names = [];
      /** @var \Drupal\checklist\Entity\ChecklistItem $checklist_item */
      foreach ($widget_state[$delta]['checklist_items'] as $name => $checklist_item) {
        $checklist_item->name = $checklist_values[$name]['name'];
        $checklist_item->title = $checklist_values[$name]['title'];

        $existing_names[] = $checklist_item->name->value;

        // If the checklist item is new, then set this item on the default_items
        // configuration.
        if ($checklist_item->isNew()) {
          $config['default_items'][$checklist_item->name->value] = [
            'name' => $checklist_item->name->value,
            'title' => $checklist_item->title->value,
            'handler' => $checklist_item->getHandler()->getPluginId(),
            'handler_configuration' => $checklist_item->getHandler()->getConfiguration(),
          ];
        }
      }

      foreach ($config['default_items'] as $name => $item_config) {
        if (!in_array($name, $existing_names)) {
          unset($config['default_items'][$name]);
        }
      }

      $item->setValue([
        'id' => $item->id,
        'configuration' => $config,
      ]);
    }
  }

  /**
   * Submit form to add a checklist item.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addChecklistItemFormSubmit(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $row_parents = array_slice($trigger['#parents'], 0, -2);

    $values = $form_state->getValue($row_parents);
    $item = \Drupal::entityTypeManager()->getStorage('checklist_item')->create([
      'checklist_type' => $trigger['#checklist_type'],
      'name' => $values['name'],
      'title' => $values['title'],
      'handler' => [
        'id' => 'simply_checkable',
        'configuration' => [],
      ]
    ]);

    $field_name = $row_parents[count($row_parents) - 3];
    $delta = $row_parents[count($row_parents) - 2];
    $widget_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $widget_state[$delta]['checklist_items'][$values['name']] = $item;
    static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);

    $input = &$form_state->getUserInput();
    NestedArray::unsetValue($input, $row_parents);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit form to remove a checklist item.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeChecklistItemFormSubmit(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $row_parents = array_slice($trigger['#parents'], 0, -2);

    $field_name = $row_parents[count($row_parents) - 3];
    $delta = $row_parents[count($row_parents) - 2];
    $ci_name = $row_parents[count($row_parents) - 1];

    $widget_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    unset($widget_state[$delta]['checklist_items'][$ci_name]);
    static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);

    $input = &$form_state->getUserInput();
    NestedArray::unsetValue($input, $row_parents);

    $form_state->setRebuild(TRUE);
  }

}
