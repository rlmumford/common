<?php

namespace Drupal\checklist\Event;

use Drupal\Core\Plugin\Context\ContextInterface;

/**
 * Event to collect contexts on a checklist.
 */
class ChecklistCollectRuntimeContextsEvent extends ChecklistEvent implements ChecklistCollectContextsEventInterface {

  /**
   * The collected contexts.
   *
   * @var \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of collected contexts.
   */
  protected $contexts = [];

  /**
   * {@inheritdoc}
   */
  public function getContexts() : array {
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function addContext(string $name, ContextInterface $context) : ChecklistCollectRuntimeContextsEvent {
    $this->contexts[$name] = $context;
    return $this;
  }

}
