<?php

namespace Drupal\checklist\Attempt;

/**
 * Internal bearer token for one worker iteration; never expose it to viewers.
 */
final class ChecklistAttemptClaim {

  /**
   * Constructs an iteration claim.
   *
   * @param \Drupal\checklist\Attempt\ChecklistAttempt $attempt
   *   Running attempt snapshot and expected journal version.
   * @param string $token
   *   Unique claim token, replaced on every acquisition and renewal.
   * @param int $expires
   *   Expiry timestamp for worker scheduling; database expiry is authoritative.
   */
  public function __construct(
    public readonly ChecklistAttempt $attempt,
    public readonly string $token,
    public readonly int $expires,
  ) {}

}
