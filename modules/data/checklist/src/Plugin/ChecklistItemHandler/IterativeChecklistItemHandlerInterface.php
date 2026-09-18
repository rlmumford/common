<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Attempt\ChecklistAttempt;

/**
 * Returns an audited item result, completing now or yielding a continuation.
 */
interface IterativeChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Performs one iteration using the prepared contexts and stored item state.
   *
   * Return local state/outcome changes rather than saving or mutating the item.
   * The runner rechecks access, contexts and its claim before applying them.
   * Provider calls happen here, outside a transaction; use provider idempotency
   * and persist run IDs through results to avoid repeating external effects.
   * The attempt ID is stable across iterations and can scope provider
   * idempotency keys. The snapshot deliberately contains no claim token.
   * Do not call this directly: use checklist.item_executor.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $attempt
   *   The running attempt snapshot for this iteration.
   */
  public function actionIteration(ChecklistAttempt $attempt): ChecklistItemResult;

}
