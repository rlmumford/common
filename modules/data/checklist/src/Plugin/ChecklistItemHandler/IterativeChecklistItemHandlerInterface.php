<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Execution\ChecklistIterationResult;
use Drupal\checklist\Attempt\ChecklistAttempt;

/**
 * Opts automatic action execution into bounded, repeatable iterations.
 */
interface IterativeChecklistItemHandlerInterface extends StatefulChecklistItemHandlerInterface {

  /**
   * Performs one iteration using the prepared contexts and stored item state.
   *
   * Return local state/outcome changes rather than saving or mutating the item.
   * The runner rechecks access, contexts and its claim before applying them.
   * Provider calls happen here, outside a transaction; use provider idempotency
   * and persist run IDs through results to avoid repeating external effects.
   * The attempt ID is stable across iterations and can scope provider
   * idempotency keys. The snapshot deliberately contains no claim token.
   * Do not call this directly: use checklist.iteration_runner.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $attempt
   *   The running attempt snapshot for this iteration.
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistIterationResult;

}
