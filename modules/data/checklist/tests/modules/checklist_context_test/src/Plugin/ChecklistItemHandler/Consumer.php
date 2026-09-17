<?php

namespace Drupal\checklist_context_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ContextAwareChecklistItemHandlerBase;

/**
 * Records the contexts seen by automatic execution.
 *
 * @ChecklistItemHandler(
 *   id = "context_consumer",
 *   label = @Translation("Context consumer"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("string", required = TRUE),
 *     "optional" = @ContextDefinition("string", required = FALSE)
 *   }
 * )
 */
class Consumer extends ContextAwareChecklistItemHandlerBase {

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
    $state = \Drupal::state();
    $runs = $state->get('checklist_context_test.runs', []);
    $runs[] = [$this->getContextValue('value'), $this->getContextValue('optional')];
    $state->set('checklist_context_test.runs', $runs);
    if (empty($this->configuration['stay_incomplete'])) {
      $this->item->setComplete(ChecklistItemInterface::METHOD_AUTO);
    }
    return $this;
  }

}
