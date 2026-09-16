<?php

namespace Drupal\typed_data_plus\Plugin\Condition;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * A condition whose owning workflow supplies its context contract.
 *
 * Definitions and values are deliberately not stored in plugin configuration.
 * Call setExpectedContexts() before building a form, and setRuntimeContexts()
 * before each execution. Optional definitions allow known but absent values.
 */
abstract class ContextAwareCondition extends ConditionPluginBase {

  /**
   * Definitions supplied by the containing workflow.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   */
  protected array $expectedContexts = [];

  /**
   * Metadata collected during the latest evaluation.
   */
  protected ?BubbleableMetadata $evaluationMetadata = NULL;

  /**
   * Declares named context slots without requiring live values.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $definitions
   *   Definitions keyed by the names used in the condition.
   *
   * @return $this
   */
  public function setExpectedContexts(array $definitions): static {
    foreach ($definitions as $definition) {
      if (!$definition instanceof ContextDefinitionInterface) {
        throw new \InvalidArgumentException('Expected Drupal context definitions.');
      }
    }
    $this->expectedContexts = $definitions;
    $this->context = [];
    $this->evaluationMetadata = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    return parent::getContextDefinitions() + $this->expectedContexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition($name) {
    return $this->getContextDefinitions()[$name] ?? parent::getContextDefinition($name);
  }

  /**
   * Replaces runtime values, applying configured selector mappings.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   Available runtime contexts. Callers must declare their definitions first.
   *
   * @return $this
   */
  public function setRuntimeContexts(array $contexts): static {
    foreach ($contexts as $context) {
      if (!$context instanceof ContextInterface) {
        throw new \InvalidArgumentException('Expected Drupal context objects.');
      }
    }
    $this->context = [];
    $this->evaluationMetadata = NULL;
    $this->contextHandler()->applyContextMapping($this, $contexts);
    return $this;
  }

  /**
   * Uses selector-aware mapping even without the site-wide adapter enabled.
   */
  protected function contextHandler() {
    return \Drupal::service('typed_data_plus.context_handler');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return array_values(array_unique(array_merge(parent::getCacheTags(), $this->evaluationMetadata?->getCacheTags() ?? [])));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return array_values(array_unique(array_merge(parent::getCacheContexts(), $this->evaluationMetadata?->getCacheContexts() ?? [])));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::mergeMaxAges(parent::getCacheMaxAge(), $this->evaluationMetadata?->getCacheMaxAge() ?? -1);
  }

}
