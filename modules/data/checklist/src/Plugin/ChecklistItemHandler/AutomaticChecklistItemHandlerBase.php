<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;

/**
 * Base for audited automatic items that return a result from one function.
 *
 * Implement actionIteration() to complete immediately or yield. State is an
 * optional separate capability; simple one-step handlers need no state schema.
 */
abstract class AutomaticChecklistItemHandlerBase extends ContextAwareChecklistItemHandlerBase implements IterativeChecklistItemHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_AUTO;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    throw new \LogicException('Use the item executor for audited automatic work.');
  }

}
