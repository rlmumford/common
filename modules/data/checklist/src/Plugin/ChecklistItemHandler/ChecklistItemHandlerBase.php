<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\ChecklistConditionEvaluator;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for checklist item handlers.
 */
abstract class ChecklistItemHandlerBase extends PluginBase implements ChecklistItemHandlerInterface, ContainerFactoryPluginInterface, DependentPluginInterface {
  use PluginWithFormsTrait;

  /**
   * The name of this checklist item.
   *
   * @var string
   */
  protected $name;

  /**
   * The checklist item object.
   *
   * @var \Drupal\checklist\Entity\ChecklistItemInterface
   *
   * @todo See if we can remove this from this class.
   */
  protected $item;

  /**
   * Constructs a checklist item handler.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\checklist\ChecklistConditionEvaluator $conditionEvaluator
   *   The condition evaluator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ChecklistConditionEvaluator $conditionEvaluator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('checklist.condition_evaluator'));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['conditions' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->conditionEvaluator->calculateDependencies($this->getConfiguration()['conditions']);
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): ChecklistItemHandlerInterface {
    $this->name = $name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setItem(ChecklistItemInterface $item): ChecklistItemHandlerInterface {
    $this->item = $item;
    $this->name = $item->getName();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItem(): ChecklistItemInterface {
    return $this->item;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): ?bool {
    return $this->evaluateCondition('applicability');
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return $this->evaluateCondition('required') ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isActionable(): bool {
    return $this->evaluateCondition('actionability') === TRUE;
  }

  /**
   * Evaluates one configured gate, defaulting to TRUE when omitted.
   */
  protected function evaluateCondition(string $gate): ?bool {
    $conditions = $this->getConfiguration()['conditions'];
    if (!array_key_exists($gate, $conditions)) {
      return TRUE;
    }
    return $this->conditionEvaluator->evaluate(
      $this->getItem()->get('checklist')->checklist,
      $conditions[$gate]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function finalizePlaceholders() {
    // Do Nothing by default.
  }

}
