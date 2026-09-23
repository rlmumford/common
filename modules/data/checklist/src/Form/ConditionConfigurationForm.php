<?php

namespace Drupal\checklist\Form;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\typed_data_plus\Plugin\Condition\BinaryConditionGroup;
use Drupal\typed_data_plus\Plugin\Condition\ConditionGroup;
use Drupal\typed_data_plus\Plugin\Condition\ContextAwareCondition;

/**
 * Embeds ordinary condition plugin forms and recursively edits their groups.
 */
class ConditionConfigurationForm {

  use StringTranslationTrait;

  public function __construct(protected ConditionManager $manager) {}

  /**
   * Builds a condition from definitions only; it never evaluates live data.
   */
  public function build(array $element, FormStateInterface $state, array $configuration, array $contexts): array {
    if (isset($contexts['checklist:entity'])) {
      $contexts['checklist'] = $contexts['checklist:entity'];
    }
    $element['#tree'] = TRUE;
    $input = ConfigurationForm::input($element, $state);
    $id = $input['id'] ?? $configuration['id'] ?? '';
    $options = ['' => $this->t('- No condition (always) -')];
    foreach ($this->manager->getDefinitions() as $key => $definition) {
      $options[$key] = $definition['label'];
    }
    $element['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Condition'),
      '#options' => $options,
      '#default_value' => $id,
    ];
    $element['update'] = ConfigurationForm::button($element['#parents'], $this->t('Update condition'));
    $element['#condition_id'] = $id;
    $element['#condition_configuration'] = ($configuration['id'] ?? '') === $id ? $configuration : [];
    $element['#condition_contexts'] = $contexts;
    if ($id === '' || !$this->manager->hasDefinition($id)) {
      return $element;
    }
    $plugin = $this->plugin($element);
    $element['settings'] = ['#type' => 'container', '#parents' => [...$element['#parents'], 'settings']];
    $substate = SubformState::createForSubform($element['settings'], $element, $state);
    $previous = $state->getTemporaryValue('gathered_contexts');
    $state->setTemporaryValue('gathered_contexts', $contexts);
    $element['settings'] = $plugin->buildConfigurationForm($element['settings'], $substate);
    $state->setTemporaryValue('gathered_contexts', $previous);
    if ($plugin instanceof ConditionGroup) {
      $children = $element['#condition_configuration']['conditions'] ?? [];
      $parents = [...$element['#parents'], 'children'];
      $element['children'] = ['#type' => 'details', '#title' => $this->t('Operands'), '#open' => TRUE];
      $rows = ConfigurationForm::rows($children, $parents, $state);
      if ($plugin instanceof BinaryConditionGroup) {
        $rows = array_pad(array_slice($rows, 0, 2), 2, ['configuration' => []]);
      }
      foreach ($rows as $index => $row) {
        $child = ['#type' => 'fieldset', '#parents' => [...$parents, $index]];
        $child_state = SubformState::createForSubform($child, $element, $state);
        $element['children'][$index] = $this->build($child, $child_state, $row['configuration'], $contexts);
      }
      if (!$plugin instanceof BinaryConditionGroup) {
        $element['children']['add'] = ConfigurationForm::button($parents, $this->t('Add operand'), TRUE);
      }
    }
    return $element;
  }

  /**
   * Creates the plugin with its containing checklist's expected contexts.
   */
  protected function plugin(array $element) {
    $plugin = $this->manager->createInstance($element['#condition_id'], $element['#condition_configuration']);
    if ($plugin instanceof ContextAwareCondition) {
      $plugin->setExpectedContexts(array_map(static fn($context) => (clone $context->getContextDefinition())->setRequired(FALSE), $element['#condition_contexts']));
    }
    return $plugin;
  }

  /**
   * Validates and returns normalized plugin configuration, or no condition.
   */
  public function configuration(array &$element, FormStateInterface $state): ?array {
    $id = $state->getValue('id', '');
    if ($id !== $element['#condition_id']) {
      $state->setError($element['id'], $this->t('Update the condition before saving its settings.'));
      return NULL;
    }
    if ($id === '') {
      return NULL;
    }
    $plugin = $this->plugin($element);
    $substate = SubformState::createForSubform($element['settings'], $element, $state);
    $plugin->validateConfigurationForm($element['settings'], $substate);
    if ($state->hasAnyErrors()) {
      return NULL;
    }
    $plugin->submitConfigurationForm($element['settings'], $substate);
    $configuration = $plugin->getConfiguration();
    if ($plugin instanceof ConditionGroup) {
      $configuration['conditions'] = [];
      foreach ($element['children'] as $index => &$child) {
        if (is_int($index)) {
          $child_state = SubformState::createForSubform($child, $element, $state);
          if ($value = $this->configuration($child, $child_state)) {
            $configuration['conditions'][] = $value;
          }
        }
      }
      unset($child);
      if (!$configuration['conditions'] || ($plugin instanceof BinaryConditionGroup && count($configuration['conditions']) !== 2)) {
        $state->setError($element['id'], $this->t('Choose operands for this group; XOR and XAnd require exactly two.'));
      }
    }
    return $configuration;
  }

}
