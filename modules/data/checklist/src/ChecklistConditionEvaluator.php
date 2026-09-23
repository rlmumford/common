<?php

namespace Drupal\checklist;

use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Condition\ConditionManager;
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
    $condition = $this->conditionManager->createInstance($configuration['id'], $configuration);
    $contexts = $this->collector->collectRuntimeContexts($checklist);
    // Provide a simple root name for condition-string property traversal.
    $contexts['checklist'] = $contexts['checklist:entity'];
    try {
      if ($condition instanceof ContextAwareCondition) {
        $definitions = [];
        foreach ($contexts as $name => $context) {
          $definitions[$name] = clone $context->getContextDefinition();
          $definitions[$name]->setRequired(FALSE);
        }
        $condition->setExpectedContexts($definitions);
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
