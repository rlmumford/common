<?php

namespace Drupal\typed_data_plus\Plugin\Condition;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\typed_data_plus\Condition\ConditionException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Composes ordinary Drupal condition plugin configurations.
 *
 * The containing editor owns adding/removing children. Each child exposes its
 * standard configuration form. Runtime instances are fresh for each evaluation.
 */
abstract class ConditionGroup extends ContextAwareCondition implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ConditionManager $conditionManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.condition'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['conditions' => []] + parent::defaultConfiguration();
  }

  /**
   * Returns configured children with their configuration-time context contract.
   *
   * @return \Drupal\Core\Condition\ConditionInterface[]
   *   Fresh plugins; callers can use their standard configuration forms.
   */
  public function getConditions(): array {
    $this->validateTree($this->configuration);
    $children = [];
    foreach ($this->configuration['conditions'] as $key => $configuration) {
      $child = $this->conditionManager->createInstance($configuration['id'], $configuration);
      if ($child instanceof ContextAwareCondition) {
        $child->setExpectedContexts($this->getContextDefinitions());
      }
      $children[$key] = $child;
    }
    return $children;
  }

  /**
   * Rejects malformed or excessively nested groups before executing children.
   */
  protected function validateTree(array $configuration, int $depth = 0): void {
    if ($depth >= 64 || !is_array($configuration['conditions'] ?? NULL)) {
      throw new ConditionException('Invalid or excessively nested condition group.');
    }
    foreach ($configuration['conditions'] as $child) {
      if (!is_array($child) || !is_string($child['id'] ?? NULL)) {
        throw new ConditionException('Each condition must contain a plugin ID.');
      }
      $definition = $this->conditionManager->getDefinition($child['id']);
      if (is_a($definition['class'], self::class, TRUE)) {
        $this->validateTree($child + ['conditions' => []], $depth + 1);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $this->evaluationMetadata = new BubbleableMetadata();
    $values = [];
    foreach ($this->getConditions() as $child) {
      if ($child instanceof ContextAwareCondition) {
        $child->setRuntimeContexts($this->getContexts());
      }
      else {
        $this->contextHandler()->applyContextMapping($child, $this->getContexts());
      }
      // No short-circuit: collect every child's metadata and surface errors.
      // execute() applies Drupal's negation exactly once.
      $values[] = (bool) $child->execute();
      $this->evaluationMetadata->addCacheableDependency($child);
    }
    return $this->combine($values);
  }

  /**
   * Combines child results, before the manager applies this group's negation.
   */
  abstract protected function combine(array $values): bool;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    foreach ($this->getConditions() as $child) {
      $child_dependencies = $child->calculateDependencies();
      $child_dependencies['module'][] = $child->getPluginDefinition()['provider'];
      foreach ($child_dependencies as $type => $names) {
        $dependencies[$type] = array_values(array_unique(array_merge($dependencies[$type] ?? [], $names)));
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('@label (@count conditions)', [
      '@label' => $this->getPluginDefinition()['label'],
      '@count' => count($this->configuration['conditions']),
    ]);
  }

}
