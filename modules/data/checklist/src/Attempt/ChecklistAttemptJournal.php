<?php

namespace Drupal\checklist\Attempt;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Durable attempt metadata and append-only transition history.
 *
 * Internal persistence API, not an authorization or execution coordinator.
 * It does not run handlers, save items, reset state or grant ownership.
 * Callers enforce access, execution policy and workspace fencing.
 */
class ChecklistAttemptJournal {

  /**
   * Constructs the journal.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The clock, using current time for long-lived workers.
   */
  public function __construct(
    protected Connection $database,
    protected UuidInterface $uuid,
    protected TimeInterface $time,
  ) {}

  /**
   * Records a queued attempt without running work or altering the item.
   *
   * Only one nonterminal attempt may exist per item. Successors require the
   * exact latest predecessor. Resume requires failure; fresh also permits
   * cancelled or superseded predecessors. Success is not implicitly reopened.
   * Unsaved items must retain their UUID in the authoritative workspace.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   The item whose UUID identifies this attempt stream.
   * @param int $initiator
   *   Initiating user ID.
   * @param int $executor
   *   Intended execution user ID.
   * @param string $path
   *   Action, action form or action operation entry point.
   * @param string|null $operation
   *   Required operation name for action operations; NULL for other paths.
   * @param string $mode
   *   Initial, resume or fresh intent.
   * @param string|null $previous
   *   Expected predecessor UUID, required for resume/fresh.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt
   *   The new queued attempt.
   *
   * @throws \Drupal\checklist\Attempt\ChecklistAttemptConflictException
   *   When the expected predecessor is no longer current.
   * @throws \DomainException
   *   When a predecessor cannot be succeeded in the requested mode.
   */
  public function create(ChecklistItemInterface $item, int $initiator, int $executor, string $path, ?string $operation = NULL, string $mode = ChecklistAttempt::INITIAL, ?string $previous = NULL): ChecklistAttempt {
    $item_uuid = $item->uuid();
    if (!Uuid::isValid($item_uuid) || $initiator < 0 || $executor < 0) {
      throw new \InvalidArgumentException('An item UUID and nonnegative account IDs are required.');
    }
    if (!in_array($mode, [ChecklistAttempt::INITIAL, ChecklistAttempt::RESUME, ChecklistAttempt::FRESH], TRUE) || (($mode === ChecklistAttempt::INITIAL) !== ($previous === NULL))) {
      throw new \InvalidArgumentException('Initial attempts have no predecessor; resume and fresh require one.');
    }
    if (!in_array($path, [ChecklistAttempt::ACTION, ChecklistAttempt::ACTION_FORM, ChecklistAttempt::ACTION_OPERATION], TRUE)) {
      throw new \InvalidArgumentException('Unknown checklist execution path.');
    }
    if ($path === ChecklistAttempt::ACTION_OPERATION ? ($operation === NULL || $operation === '' || mb_strlen($operation) > 128) : $operation !== NULL) {
      throw new \InvalidArgumentException('Only action operations require an operation name, up to 128 characters.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $latest = $this->latest($item);
      if ($latest?->id !== $previous) {
        throw new ChecklistAttemptConflictException('The current attempt has changed.');
      }
      $allowed = $mode === ChecklistAttempt::RESUME ? [ChecklistAttempt::FAILED] : [
        ChecklistAttempt::FAILED,
        ChecklistAttempt::CANCELLED,
        ChecklistAttempt::SUPERSEDED,
      ];
      if ($latest && !in_array($latest->status, $allowed, TRUE)) {
        throw new \DomainException('The previous attempt cannot be succeeded in this mode.');
      }
      $id = $this->uuid->generate();
      if ($previous === NULL) {
        $this->database->insert('checklist_attempt_head')->fields([
          'item_uuid' => $item_uuid,
          'attempt' => $id,
        ])->execute();
      }
      else {
        $updated = $this->database->update('checklist_attempt_head')
          ->fields(['attempt' => $id])
          ->condition('item_uuid', $item_uuid)
          ->condition('attempt', $previous)
          ->execute();
        if (!$updated) {
          throw new ChecklistAttemptConflictException('The current attempt has changed.');
        }
      }
      $now = $this->time->getCurrentTime();
      $this->database->insert('checklist_attempt')->fields([
        'id' => $id,
        'item_uuid' => $item_uuid,
        'previous' => $previous,
        'mode' => $mode,
        'status' => ChecklistAttempt::QUEUED,
        'version' => 1,
        'initiator' => $initiator,
        'executor' => $executor,
        'path' => $path,
        'operation' => $operation,
        'created' => $now,
        'changed' => $now,
      ])->execute();
      $this->appendEvent($id, 1, NULL, ChecklistAttempt::QUEUED, $initiator, $now, '');
      return $this->load($id);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      if ($exception instanceof IntegrityConstraintViolationException) {
        throw new ChecklistAttemptConflictException('An attempt was concurrently created.', 0, $exception);
      }
      throw $exception;
    }
  }

  /**
   * Atomically transitions the expected version and appends its history event.
   *
   * Terminal attempts are immutable. Delayed or duplicate transitions conflict;
   * they never update a successor. This version fences journal writes only,
   * not item writes or external effects. There is no worker lease in this API.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $expected
   *   Previously read attempt snapshot.
   * @param string $status
   *   New execution status.
   * @param int $actor
   *   User ID responsible for the transition, not inferred from current user.
   * @param string $reason
   *   Optional safe explanation, at most 512 characters. Never raw exceptions,
   *   working state, prompts or credentials.
   *
   * @return \Drupal\checklist\Attempt\ChecklistAttempt
   *   Updated snapshot.
   *
   * @throws \Drupal\checklist\Attempt\ChecklistAttemptConflictException
   *   When the expected version is stale or the attempt does not exist.
   * @throws \DomainException
   *   When the requested transition is not allowed.
   */
  public function transition(ChecklistAttempt $expected, string $status, int $actor, string $reason = ''): ChecklistAttempt {
    if ($actor < 0 || mb_strlen($reason) > 512) {
      throw new \InvalidArgumentException('A nonnegative actor and a reason of at most 512 characters are required.');
    }
    $transaction = $this->database->startTransaction();
    try {
      $current = $this->load($expected->id);
      if (!$current || $current->version !== $expected->version) {
        throw new ChecklistAttemptConflictException('The attempt version has changed.');
      }
      if (!$current->canTransitionTo($status)) {
        throw new \DomainException('The attempt transition is not allowed.');
      }
      $now = $this->time->getCurrentTime();
      $version = $current->version + 1;
      $updated = $this->database->update('checklist_attempt')
        ->fields(['status' => $status, 'version' => $version, 'changed' => $now])
        ->condition('id', $current->id)
        ->condition('version', $current->version)
        ->execute();
      if (!$updated) {
        throw new ChecklistAttemptConflictException('The attempt version has changed.');
      }
      $this->appendEvent($current->id, $version, $current->status, $status, $actor, $now, $reason);
      return $this->load($current->id);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Loads internal metadata; adapters must authorize the item's host first.
   */
  public function load(string $id): ?ChecklistAttempt {
    $row = $this->database->select('checklist_attempt', 'a')->fields('a')->condition('id', $id)->execute()->fetchAssoc();
    return $row ? new ChecklistAttempt(
      $row['id'], $row['item_uuid'], $row['previous'], $row['mode'],
      $row['status'], (int) $row['version'], (int) $row['initiator'],
      (int) $row['executor'], $row['path'], $row['operation'],
      (int) $row['created'], (int) $row['changed'],
    ) : NULL;
  }

  /**
   * Loads the latest attempt without acquiring a lock or starting work.
   */
  public function latest(ChecklistItemInterface $item): ?ChecklistAttempt {
    $id = $this->database->select('checklist_attempt_head', 'h')
      ->fields('h', ['attempt'])->condition('item_uuid', $item->uuid())
      ->execute()->fetchField();
    return $id ? $this->load($id) : NULL;
  }

  /**
   * Reads bounded internal history in version order, with an exclusive cursor.
   *
   * Rows contain version, from/to status, actor, timestamp and reason.
   * No access checks are performed; do not expose these rows directly to users.
   */
  public function history(string $id, int $after_version = 0, int $limit = 50): array {
    if ($after_version < 0 || $limit < 1 || $limit > 100) {
      throw new \InvalidArgumentException('History needs a nonnegative cursor and a limit between 1 and 100.');
    }
    $rows = $this->database->select('checklist_attempt_event', 'e')
      ->fields('e', ['version', 'from_status', 'to_status', 'actor', 'created', 'reason'])
      ->condition('attempt', $id)->condition('version', $after_version, '>')
      ->orderBy('version')->range(0, $limit)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      foreach (['version', 'actor', 'created'] as $key) {
        $row[$key] = (int) $row[$key];
      }
    }
    unset($row);
    return $rows;
  }

  /**
   * Appends metadata in the same transaction as the attempt change.
   */
  protected function appendEvent(string $id, int $version, ?string $from, string $to, int $actor, int $created, string $reason): void {
    $this->database->insert('checklist_attempt_event')->fields([
      'attempt' => $id,
      'version' => $version,
      'from_status' => $from,
      'to_status' => $to,
      'actor' => $actor,
      'created' => $created,
      'reason' => $reason,
    ])->execute();
  }

}
