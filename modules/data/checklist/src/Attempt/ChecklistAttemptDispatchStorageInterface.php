<?php

namespace Drupal\checklist\Attempt;

/**
 * Storage boundary for scheduling already-authorized automatic attempts.
 *
 * This is dispatch storage, not the complete journal/claim storage contract.
 * Replacing it must coordinate with the authoritative attempt backend: waiting
 * commits release reservations atomically with due times and attempt versions.
 * A separate Redis delivery index alone cannot supply those guarantees.
 */
interface ChecklistAttemptDispatchStorageInterface {

  /**
   * Selects due work and atomically reserves each returned delivery.
   *
   * Select only committed initial action attempts that are queued/waiting, due,
   * unclaimed and not reserved for dispatch. Recheck eligibility and version
   * when reserving: concurrent callers must not reserve the same version while
   * its reservation is live. Reservations expire after five minutes so a crash
   * before enqueue or a lost message can be repaired by a later scan.
   *
   * Order by dispatch expiry (zero first), then due time, creation time and
   * attempt UUID. A waiting commit resets dispatch expiry to zero. This is
   * delivery fairness, not strict FIFO or an execution-priority policy.
   *
   * Reservations must be durable before returning. Reject calls inside an open
   * submission/result transaction; never expose uncommitted work to transports.
   * Do not change execution status/version/history, invoke a handler, perform
   * access checks or send messages. Execution authorization belongs to the
   * runner; dispatch reservation is distinct from its iteration claim.
   *
   * @param int $limit
   *   Maximum deliveries, from one to 100. Contention may return fewer.
   *
   * @return array
   *   Deliveries containing only 'attempt' (UUID string) and 'version' (integer).
   *
   * @throws \InvalidArgumentException
   *   If the batch limit is outside the supported range.
   * @throws \LogicException
   *   If reservations cannot be committed before returning.
   */
  public function reserveDue(int $limit = 50): array;

}
