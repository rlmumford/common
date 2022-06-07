<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\ChecklistInterface;
use Drupal\Core\Plugin\Context\ContextInterface;

/**
 * Interface for checklist context collection events.
 */
interface ChecklistCollectContextsEventInterface {

  /**
   * Get the collected context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The list of contexts.
   */
  public function getContexts() : array;

  /**
   * Add a context to the available contexts.
   *
   * @param string $name
   *   The context name.
   * @param \Drupal\Core\Plugin\Context\ContextInterface $context
   *   The context object.
   *
   * @return \Drupal\checklist\Event\ChecklistCollectContextsEventInterface
   *   The same event object.
   */
  public function addContext(string $name, ContextInterface $context) : ChecklistCollectContextsEventInterface;

  /**
   * Get the checklist.
   *
   * @return \Drupal\checklist\ChecklistInterface
   *   The checklist.
   */
  public function getChecklist() : ChecklistInterface;

}
