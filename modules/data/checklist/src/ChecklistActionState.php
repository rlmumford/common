<?php

namespace Drupal\checklist;

/**
 * A safe, read-only progress projection supplied by an item handler.
 */
final class ChecklistActionState {

  /**
   * Constructs an action progress snapshot.
   *
   * @param string|null $stage
   *   A machine-readable stage, or NULL when unknown.
   * @param string|null $message
   *   Plain text for the current viewer, never HTML or raw error details.
   * @param int|null $completed
   *   Completed units, or NULL when progress cannot be counted.
   * @param int|null $total
   *   Total units, or NULL when unknown.
   * @param int|null $updatedAt
   *   Unix timestamp of the underlying progress update, not the read time.
   * @param bool $inputRequired
   *   Whether this action is waiting for input.
   */
  public function __construct(
    public readonly ?string $stage = NULL,
    public readonly ?string $message = NULL,
    public readonly ?int $completed = NULL,
    public readonly ?int $total = NULL,
    public readonly ?int $updatedAt = NULL,
    public readonly bool $inputRequired = FALSE,
  ) {
    if (($completed !== NULL && $completed < 0) || ($total !== NULL && $total < 0) || ($updatedAt !== NULL && $updatedAt < 0)) {
      throw new \InvalidArgumentException('Progress counts and timestamps cannot be negative.');
    }
    if ($completed !== NULL && $total !== NULL && $completed > $total) {
      throw new \InvalidArgumentException('Completed progress cannot exceed its total.');
    }
  }

  /**
   * Returns only the public progress fields, without arbitrary state payloads.
   */
  public function toArray(): array {
    return [
      'stage' => $this->stage,
      'message' => $this->message,
      'completed' => $this->completed,
      'total' => $this->total,
      'updated_at' => $this->updatedAt,
      'input_required' => $this->inputRequired,
    ];
  }

}
