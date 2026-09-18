<?php

namespace Drupal\checklist\Attempt;

/**
 * Immutable attempt snapshot, separate from item disposition and ownership.
 */
final class ChecklistAttempt {

  public const QUEUED = 'queued';
  public const RUNNING = 'running';
  public const WAITING = 'waiting';
  public const SUCCEEDED = 'succeeded';
  public const FAILED = 'failed';
  public const CANCELLED = 'cancelled';
  public const SUPERSEDED = 'superseded';

  public const ACTION = 'action';
  public const ACTION_FORM = 'action_form';
  public const ACTION_OPERATION = 'action_operation';

  public const INITIAL = 'initial';
  public const RESUME = 'resume';
  public const FRESH = 'fresh';

  /**
   * Constructs a stored attempt snapshot.
   *
   * @param string $id
   *   Attempt UUID.
   * @param string $itemUuid
   *   Checklist item UUID, including for an unsaved item.
   * @param string|null $previous
   *   Predecessor attempt UUID.
   * @param string $mode
   *   Initial, resume or fresh intent; does not itself change item state.
   * @param string $status
   *   Execution status, not the item's disposition.
   * @param int $version
   *   Monotonic transition version, used for conditional writes.
   * @param int $initiator
   *   Initiating user ID; zero represents anonymous/system initiation.
   * @param int $executor
   *   Intended execution user ID, distinct from the workspace owner.
   * @param string $path
   *   Action, action form or action operation entry point.
   * @param string|null $operation
   *   Operation name for action operations, without input payloads.
   * @param int $created
   *   Creation timestamp.
   * @param int $changed
   *   Last transition timestamp.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $itemUuid,
    public readonly ?string $previous,
    public readonly string $mode,
    public readonly string $status,
    public readonly int $version,
    public readonly int $initiator,
    public readonly int $executor,
    public readonly string $path,
    public readonly ?string $operation,
    public readonly int $created,
    public readonly int $changed,
  ) {}

  /**
   * Whether the attempt is closed to further transitions.
   */
  public function isTerminal(): bool {
    return in_array($this->status, [self::SUCCEEDED, self::FAILED, self::CANCELLED, self::SUPERSEDED], TRUE);
  }

  /**
   * Whether the requested transition is valid for this stored status.
   */
  public function canTransitionTo(string $status): bool {
    $transitions = [
      self::QUEUED => [self::RUNNING, self::CANCELLED, self::SUPERSEDED],
      self::RUNNING => [self::WAITING, self::SUCCEEDED, self::FAILED, self::CANCELLED, self::SUPERSEDED],
      self::WAITING => [self::RUNNING, self::FAILED, self::CANCELLED, self::SUPERSEDED],
    ];
    return in_array($status, $transitions[$this->status] ?? [], TRUE);
  }

}
