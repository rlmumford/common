<?php

namespace Drupal\typed_data_plus\Plugin\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\typed_data_plus\Condition\ConditionEvaluator;
use Drupal\typed_data_plus\Condition\ConditionException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Evaluates a condition string using the workflow's declared contexts.
 *
 * @Condition(
 *   id = "condition_string",
 *   label = @Translation("Condition string")
 * )
 */
class ConditionString extends ContextAwareCondition implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConditionEvaluator $evaluator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('typed_data_plus.condition_evaluator'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['condition_string' => ''] + parent::defaultConfiguration();
  }

  /**
   * Validates selectors against definitions, without fetching live values.
   */
  public function validate(): void {
    $definitions = [];
    foreach ($this->getContextDefinitions() as $name => $definition) {
      $definitions[$name] = $definition->getDataDefinition();
    }
    $this->evaluator->validate($this->configuration['condition_string'], $definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $this->evaluationMetadata = NULL;
    $contexts = [];
    foreach ($this->getContexts() as $name => $context) {
      // Force required-value checks; optional NULL still has a data definition.
      $context->getContextValue();
      $contexts[$name] = $context->getContextData();
    }
    $result = $this->evaluator->evaluate($this->configuration['condition_string'], $contexts);
    $this->evaluationMetadata = $result;
    return $result->isMet();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['condition_string'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Condition string'),
      '#default_value' => $this->configuration['condition_string'],
      '#description' => $this->t('Available contexts: @names', ['@names' => implode(', ', array_keys($this->getContextDefinitions()))]),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $candidate = clone $this;
    $candidate->configuration['condition_string'] = $form_state->getValue('condition_string');
    try {
      $candidate->validate();
    }
    catch (ConditionException $exception) {
      $form_state->setError($form['condition_string'], $exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['condition_string'] = $form_state->getValue('condition_string');
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $expression = $this->configuration['condition_string'];
    return $this->isNegated() ? $this->t('NOT: @expression', ['@expression' => $expression]) : $expression;
  }

}
