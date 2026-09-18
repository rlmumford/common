<?php

namespace Drupal\checklist\Attempt;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Coordinates bounded worker iterations within a durable attempt.
 *
 * Internal worker API. Callers own account switching, access, gates and
 * workspace ownership. Claims do not fence legacy saves or external effects.
 */
class ChecklistAttemptClaims {

  /**
   * Constructs the claim coordinator.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The same connection used by the journal and result application.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   Attempt metadata and transition history.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   Claim token generator.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Current worker time, not the request start time.
   */
  public function __construct(
    protected Connection $database,
    protected ChecklistAttemptJournal $journal,
    protected UuidInterface $uuid,
    protected TimeInterface $time,
  ) {}

  /**
   * Claims a due queued/waiting attempt and records its running transition.
   *
   * The claim commits before return. Do network/handler work after this method,
   * outside a transaction, then apply local results through commit(). Running
   * attempts, including expired ones, are never implicitly claimed again.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $expected
   *   Latest attempt snapshot.
   * @param int $lease_seconds
   *   Lease duration, from one second to one day.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttemptClaim
   *   The claim required for renewal and result application.
   */
  public function claim(ChecklistAttempt $expected, int $lease_seconds = 300): ChecklistAttemptClaim {
    $this->assertStandalone();
    $this->validateDuration($lease_seconds);
    $transaction = $this->database->startTransaction();
    try {
      $current = $this->current($expected);
      if (!in_array($current->status, [ChecklistAttempt::QUEUED, ChecklistAttempt::WAITING], TRUE)) {
        throw new \DomainException('Only queued or waiting attempts can be claimed.');
      }
      $running = $this->journal->transition($current, ChecklistAttempt::RUNNING, $current->executor);
      $token = $this->uuid->generate();
      $expires = $this->time->getCurrentTime() + $lease_seconds;
      $this->database->update('checklist_attempt')->fields([
        'claim_token' => $token,
        'claim_expires' => $expires,
        'available' => 0,
      ])->condition('id', $running->id)->execute();
      return new ChecklistAttemptClaim($running, $token, $expires);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Renews a live claim, invalidating the old handle without a new attempt.
   *
   * Heartbeats do not append journal transitions. Workers must retain the new
   * token. Expired claims cannot be renewed or resurrected.
   */
  public function renew(ChecklistAttemptClaim $claim, int $lease_seconds = 300): ChecklistAttemptClaim {
    $this->assertStandalone();
    $this->validateDuration($lease_seconds);
    $transaction = $this->database->startTransaction();
    try {
      $current = $this->current($claim->attempt);
      $expires = $this->expiry($claim);
      $now = $this->time->getCurrentTime();
      $next_expiry = max($expires, $now + $lease_seconds);
      $token = $this->uuid->generate();
      $updated = $this->leaseUpdate($claim, $now)->fields([
        'claim_token' => $token,
        'claim_expires' => $next_expiry,
      ])->execute();
      if (!$updated) {
        throw new ChecklistAttemptConflictException('The iteration claim has changed or expired.');
      }
      return new ChecklistAttemptClaim($current, $token, $next_expiry);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Applies local results and releases the claim in one short transaction.
   *
   * Use waiting with a delay for another iteration, or a terminal status to end
   * the attempt. The callback must only perform short transactional writes on
   * this connection, recheck access/gates and reload authoritative entities.
   * Never invoke a provider, run a handler or send a message from the callback.
   * On rollback, discard callback-mutated objects and reload storage; database
   * rollback cannot restore PHP objects or external effects.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttemptClaim $claim
   *   The current, unexpired iteration claim.
   * @param string $status
   *   Waiting, succeeded, failed, cancelled or superseded.
   * @param callable|null $apply
   *   Optional local result application, called without arguments.
   * @param int $delay
   *   Seconds before the next waiting iteration is eligible; otherwise zero.
   * @param string $reason
   *   Safe journal explanation, not a raw error or state dump.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt
   *   The updated attempt; waiting keeps the same attempt identity.
   */
  public function commit(ChecklistAttemptClaim $claim, string $status, ?callable $apply = NULL, int $delay = 0, string $reason = ''): ChecklistAttempt {
    $this->assertStandalone();
    $allowed = [
      ChecklistAttempt::WAITING,
      ChecklistAttempt::SUCCEEDED,
      ChecklistAttempt::FAILED,
      ChecklistAttempt::CANCELLED,
      ChecklistAttempt::SUPERSEDED,
    ];
    if (!in_array($status, $allowed, TRUE) || $delay < 0 || ($status !== ChecklistAttempt::WAITING && $delay !== 0)) {
      throw new \InvalidArgumentException('Commit requires waiting or a terminal status; only waiting accepts a delay.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $expires = $this->expiry($claim);
      $current = $this->current($claim->attempt);
      $now = $this->time->getCurrentTime();
      $updated = $this->leaseUpdate($claim, $now)->fields([
        'claim_token' => NULL,
        'claim_expires' => 0,
        'available' => $status === ChecklistAttempt::WAITING ? $now + $delay : 0,
        'dispatch_expires' => 0,
      ])->execute();
      if (!$updated) {
        throw new ChecklistAttemptConflictException('The iteration claim has changed or expired.');
      }
      // This row stays locked until both history and local results commit.
      $result = $this->journal->transition($current, $status, $current->executor, $reason);
      if ($apply) {
        $apply();
      }
      if ($this->time->getCurrentTime() >= $expires) {
        throw new ChecklistAttemptConflictException('The claim expired during result application.');
      }
      return $result;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Records an expired iteration as failed without rerunning its work.
   *
   * State is retained. A supervisor must authorize reconciliation/retry before
   * starting a successor. This records failure only; it does not reset an item.
   * The actor identifies the supervisor, not the previous worker's executor.
   */
  public function expire(ChecklistAttempt $expected, int $actor): ChecklistAttempt {
    $this->assertStandalone();
    $transaction = $this->database->startTransaction();
    try {
      $current = $this->current($expected);
      $updated = $this->database->update('checklist_attempt')
        ->fields(['claim_token' => NULL, 'claim_expires' => 0])
        ->condition('id', $current->id)->condition('version', $current->version)
        ->condition('status', ChecklistAttempt::RUNNING)
        ->isNotNull('claim_token')
        ->condition('claim_expires', $this->time->getCurrentTime(), '<=')
        ->execute();
      if (!$updated) {
        throw new ChecklistAttemptConflictException('There is no matching expired claim.');
      }
      return $this->journal->transition($current, ChecklistAttempt::FAILED, $actor, 'Iteration claim expired; reconcile external effects before retrying.');
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Lists due attempt IDs for an internal scheduler; does not grant a claim.
   *
   * Eligibility excludes expired running claims. Every returned ID must still
   * be authorized and claimed before work starts. No access filtering is done.
   */
  public function due(int $limit = 50): array {
    if ($limit < 1 || $limit > 100) {
      throw new \InvalidArgumentException('The due limit must be between 1 and 100.');
    }
    return $this->database->select('checklist_attempt', 'a')->fields('a', ['id'])
      ->condition('status', [ChecklistAttempt::QUEUED, ChecklistAttempt::WAITING], 'IN')
      ->isNull('claim_token')->condition('available', $this->time->getCurrentTime(), '<=')
      ->orderBy('available')->orderBy('created')->orderBy('id')
      ->range(0, $limit)->execute()->fetchCol();
  }

  /**
   * Reads the authoritative expiry for a matching, live claim.
   */
  protected function expiry(ChecklistAttemptClaim $claim): int {
    $expires = $this->database->select('checklist_attempt', 'a')->fields('a', ['claim_expires'])
      ->condition('id', $claim->attempt->id)->condition('version', $claim->attempt->version)
      ->condition('claim_token', $claim->token)->condition('status', ChecklistAttempt::RUNNING)
      ->execute()->fetchField();
    if ($expires === FALSE || (int) $expires <= $this->time->getCurrentTime()) {
      throw new ChecklistAttemptConflictException('The iteration claim has changed or expired.');
    }
    return (int) $expires;
  }

  /**
   * Builds an atomic conditional write for the current lease.
   */
  protected function leaseUpdate(ChecklistAttemptClaim $claim, int $now) {
    return $this->database->update('checklist_attempt')
      ->condition('id', $claim->attempt->id)->condition('version', $claim->attempt->version)
      ->condition('claim_token', $claim->token)->condition('status', ChecklistAttempt::RUNNING)
      ->condition('claim_expires', $now, '>');
  }

  /**
   * Loads the authoritative snapshot, rejecting stale expected versions.
   */
  protected function current(ChecklistAttempt $expected): ChecklistAttempt {
    $current = $this->journal->load($expected->id);
    if (!$current || $current->version !== $expected->version) {
      throw new ChecklistAttemptConflictException('The attempt version has changed.');
    }
    return $current;
  }

  /**
   * Validates a bounded positive lease duration.
   */
  protected function validateDuration(int $seconds): void {
    if ($seconds < 1 || $seconds > 86400) {
      throw new \InvalidArgumentException('The lease must last between one second and one day.');
    }
  }

  /**
   * Whether a claim can be committed before invoking external work.
   *
   * Inline execution requires the claim to be durable before the handler runs.
   * If the caller already owns an outer database transaction, the claim could
   * be rolled back after the handler has performed an external side effect.
   * The executor therefore defers the attempt in that situation; this method
   * describes that transaction safety check and does not decide whether an
   * item is otherwise suitable for inline execution.
   */
  public function canAcquireCommittedClaim(): bool {
    return !$this->database->inTransaction();
  }

  /**
   * Prevents returning uncommitted claims to workers doing external work.
   */
  protected function assertStandalone(): void {
    if ($this->database->inTransaction()) {
      throw new \LogicException('Iteration coordination cannot run inside an existing transaction.');
    }
  }

}
