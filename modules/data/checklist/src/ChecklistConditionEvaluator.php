<?php

namespace Drupal\checklist;

use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\typed_data_plus\Plugin\Condition\ConditionGroup;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\typed_data_plus\Plugin\Condition\ContextAwareCondition;

/**
 * Evaluates native Drupal conditions against current checklist contexts.
 */
class ChecklistConditionEvaluator {

  /**
   * Constructs the evaluator.
   *
   * @param \Drupal\Core\Condition\ConditionManager $conditionManager
   *   The condition plugin manager.
   * @param \Drupal\checklist\ChecklistContextCollectorInterface $collector
   *   The checklist context collector.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   The selector-aware context handler.
   */
  public function __construct(
    protected ConditionManager $conditionManager,
    protected ChecklistContextCollectorInterface $collector,
    protected ContextHandlerInterface $contextHandler,
  ) {}

  /**
   * Executes a fresh condition; unavailable required contexts return NULL.
   *
   * Invalid configuration and plugin failures propagate. Condition strings can
   * test missing optional outcomes explicitly with exists/empty.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist whose current contexts should be used.
   * @param array $configuration
   *   A Drupal condition configuration, including its plugin ID.
   *
   * @return bool|null
   *   The condition result, or NULL for unavailable required contexts.
   */
  public function evaluate(ChecklistInterface $checklist, array $configuration): ?bool {
    if (!is_string($configuration['id'] ?? NULL) || $configuration['id'] === '') {
      throw new \InvalidArgumentException('Checklist conditions require a plugin ID.');
    }
    $contexts = $this->collector->collectRuntimeContexts($checklist);
    // Provide a simple root name for condition-string property traversal.
    $contexts['checklist'] = $contexts['checklist:entity'];
    $condition = $this->createCondition($configuration, $contexts);
    try {
      if ($condition instanceof ContextAwareCondition) {
        $condition->setRuntimeContexts($contexts);
      }
      else {
        $this->contextHandler->applyContextMapping($condition, $contexts);
      }
      return (bool) $condition->execute();
    }
    catch (MissingValueContextException) {
      return NULL;
    }
  }

  /**
   * Creates a condition with fixed caller contexts, including nested groups.
   *
   * Workflow-supplied contexts are sources, not remappable plugin inputs.
   * Intrinsic inputs declared by a plugin retain normal context mapping.
   */
  public function createCondition(array $configuration, array $contexts, int $depth = 0): ConditionInterface {
    if ($depth >= 64) {
      throw new \InvalidArgumentException('Condition groups are nested too deeply.');
    }
    $condition = $this->conditionManager->createInstance($configuration['id'], $configuration);
    if ($condition instanceof ContextAwareCondition) {
      $definitions = array_map(static fn($context) => (clone $context->getContextDefinition())->setRequired(FALSE), $contexts);
      $condition->setExpectedContexts($definitions);
      $fixed = array_diff_key($contexts, $condition->getPluginDefinition()['context_definitions'] ?? []);
      $condition->setContextMapping(array_diff_key($condition->getContextMapping(), $fixed));
    }
    if ($condition instanceof ConditionGroup) {
      $configuration = $condition->getConfiguration();
      foreach ($configuration['conditions'] as $key => $child) {
        $configuration['conditions'][$key] = $this->createCondition($child, $contexts, $depth + 1)->getConfiguration();
      }
      $condition->setConfiguration($configuration);
    }
    return $condition;
  }

  /**
   * Collects dependencies without evaluating conditions or loading contexts.
   */
  public function calculateDependencies(array $configurations): array {
    $dependencies = [];
    foreach ($configurations as $configuration) {
      $condition = $this->conditionManager->createInstance($configuration['id'], $configuration);
      $declared = $condition->calculateDependencies();
      $declared['module'][] = $condition->getPluginDefinition()['provider'];
      foreach ($declared as $type => $names) {
        $dependencies[$type] = array_values(array_unique(array_merge($dependencies[$type] ?? [], $names)));
      }
    }
    return $dependencies;
  }

}
