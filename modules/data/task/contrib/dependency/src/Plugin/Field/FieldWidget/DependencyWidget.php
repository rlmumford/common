<?php

namespace Drupal\task_dependency\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\task_dependency\DependencyEditor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edits event subscriptions directly instead of selecting dependency records.
 *
 * @FieldWidget(
 *   id = "task_dependencies",
 *   label = @Translation("Task dependencies"),
 *   field_types = {"entity_reference"}
 * )
 */
class DependencyWidget extends WidgetBase {

  /**
   * Constructs the widget.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, protected DependencyEditor $editor, protected EntityTypeManagerInterface $entities) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('task_dependency.editor'), $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $definition) {
    return $definition->getSetting('target_type') === 'task_dependency';
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $parents = $form['#parents'];
    $name = $items->getName();
    if (!static::getWidgetState($parents, $name, $form_state)) {
      static::setWidgetState($parents, $name, $form_state, [
        'items_count' => max(0, count($items) - 1),
        'array_parents' => [],
      ]);
    }
    $element = parent::form($items, $form, $form_state, $get_delta);
    $element['#attributes']['class'][] = 'task-dependencies-widget';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $rows = $this->editor->values($items->getEntity());
    $row = $rows[$delta] ?? [
      'id' => '',
      'trigger' => 'task.resolved',
      'action' => 'activate',
      'entity_type' => 'task',
      'entity_id' => '',
      'field' => 'status',
      'property' => 'value',
      'value' => '',
      'follow_replacement' => FALSE,
    ];
    $parents = array_merge($form['#parents'], [$items->getName(), $delta]);
    $user_input = $form_state->getUserInput() ?? [];
    $trigger = $form_state->getTriggeringElement();
    if (($trigger['#parents'] ?? []) === array_merge($parents, ['entity_type'])) {
      // An autocomplete ID from the old entity type cannot be reused safely.
      NestedArray::setValue($user_input, array_merge($parents, ['entity_id']), '');
      $form_state->setUserInput($user_input);
    }
    $input = NestedArray::getValue($user_input, $parents) ?? [];
    $type = $input['entity_type'] ?? $row['entity_type'];
    $wrapper = 'dependency-' . substr(hash('sha256', implode(':', $parents)), 0, 16);
    $element += ['#type' => 'details', '#open' => TRUE];
    $element['#title'] = $this->t('Dependency @number', ['@number' => $delta + 1]);
    $element['#prefix'] = '<div id="' . $wrapper . '">';
    $element['#suffix'] = '</div>';
    $element['#attached']['library'][] = 'task_dependency/editor';
    $element['#attributes']['class'][] = 'task-dependency-editor';
    $element['id'] = ['#type' => 'hidden', '#value' => $row['id']];
    $element['trigger'] = [
      '#type' => 'select',
      '#title' => $this->t('When'),
      '#options' => [
        'task.resolved' => $this->t('Task resolves'),
        'entity.state' => $this->t('Entity enters a state'),
      ],
      '#default_value' => $row['trigger'],
    ];
    $element['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Then'),
      '#options' => [
        'activate' => $this->t('Allow this task to activate'),
        'invalidate' => $this->t('Invalidate this task'),
      ],
      '#default_value' => $row['action'],
    ];
    $types = [];
    foreach ($this->entities->getDefinitions() as $id => $definition) {
      if ($definition->entityClassImplements('Drupal\Core\Entity\FieldableEntityInterface') && $definition->getKey('uuid') && $id !== 'task_dependency') {
        $types[$id] = $definition->getLabel();
      }
    }
    $element['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target type'),
      '#options' => $types,
      '#default_value' => $type,
      '#ajax' => [
        'callback' => [
          static::class,
          'rebuildRow',
        ],
        'wrapper' => $wrapper,
      ],
    ];
    $target = $type === $row['entity_type'] && $row['entity_id'] !== '' ? $this->entities->getStorage($type)->load($row['entity_id']) : NULL;
    $element['entity_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Wait on'),
      '#target_type' => isset($types[$type]) ? $type : 'task',
      '#default_value' => $target,
    ];
    $selector = ':input[name="' . array_shift($parents) . '[' . implode('][', $parents) . '][trigger]"]';
    foreach (['field' => 'State field', 'property' => 'State property', 'value' => 'Qualifying value'] as $key => $label) {
      $element[$key] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $row[$key],
        '#states' => ['visible' => [$selector => ['value' => 'entity.state']]],
      ];
    }
    $element['follow_replacement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Follow explicit replacement work'),
      '#default_value' => $row['follow_replacement'],
    ];
    if ($row['id']) {
      $met = (bool) $items[$delta]->entity->get('met')->value;
      $element['status'] = [
        '#type' => 'item',
        '#title' => $this->t('Status'),
        '#markup' => $met ? $this->t('Met') : $this->t('Waiting for event'),
      ];
    }
    $element['hint'] = ['#markup' => '<p>' . $this->t('Changes are saved with the task. All activation dependencies must be met; any invalidation match takes precedence.') . '</p>'];
    return $element;
  }

  /**
   * Refreshes the autocomplete after choosing another entity type.
   */
  public static function rebuildRow(array $form, FormStateInterface $form_state): array {
    return NestedArray::getValue($form, array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -1));
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $rows = [];
    foreach ($values as $value) {
      if (!empty($value['remove']) || empty($value['entity_id'])) {
        continue;
      }
      unset($value['remove'], $value['_weight'], $value['_original_delta'], $value['status'], $value['hint'], $value['_actions']);
      $value['entity_id'] = (string) $value['entity_id'];
      $value['follow_replacement'] = (bool) $value['follow_replacement'];
      $rows[] = $value;
    }
    $task = $form_state->get('task_dependency.widget_task');
    return $this->editor->prepare($task, $rows);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $form_state->set('task_dependency.widget_task', $items->getEntity());
    try {
      parent::extractFormValues($items, $form, $form_state);
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName($items->getName(), $exception->getMessage());
    }
  }

}
