<?php

namespace Drupal\checklist\Execution;

use Drupal\checklist\Attempt\ChecklistAttempt;

/**
 * Local changes to apply after one bounded automatic action iteration.
 */
final class ChecklistItemResult {

  /**
   * Constructs the iteration result.
   *
   * @param string $status
   *   Waiting, succeeded or failed.
   * @param array $state
   *   Named working-state updates; omitted values remain unchanged.
   * @param array $outcomes
   *   Named published outcomes; omitted values remain unchanged.
   * @param int $delay
   *   Seconds until a waiting continuation becomes due.
   * @param string $reason
   *   Safe history explanation, never provider payloads or exception dumps.
   * @param \Closure|null $persist
   *   Optional short transactional writes on the checklist connection, applied
   *   after claim, access and input checks and before storing outcomes. Never
   *   run template/provider work or external effects here. The callback is
   *   request-local and is not serialized as working state.
   */
  public function __construct(
    public readonly string $status,
    public readonly array $state = [],
    public readonly array $outcomes = [],
    public readonly int $delay = 0,
    public readonly string $reason = '',
    public readonly ?\Closure $persist = NULL,
  ) {
    if (!in_array($status, [ChecklistAttempt::WAITING, ChecklistAttempt::SUCCEEDED, ChecklistAttempt::FAILED], TRUE) || $delay < 0 || ($status !== ChecklistAttempt::WAITING && $delay !== 0) || mb_strlen($reason) > 512) {
      throw new \InvalidArgumentException('Invalid iteration disposition, delay or history reason.');
    }
  }

}
