<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

/**
 * Interface for checklist item handlers that expect outcomes.
 */
interface ExpectedOutcomeChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Get a list of typed data definitions for expected outcomes.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The expected outcome, keyed by name.
   */
  public function expectedOutcomeDefinitions() : array;

}
