<?php

namespace Drupal\checklist\Workspace;

/**
 * Immutable snapshot of an interaction workspace ownership lease.
 */
final class ChecklistWorkspaceLease {

  public function __construct(
    public readonly ChecklistWorkspaceAddress $address,
    public readonly int $owner,
    public readonly int $generation,
    public readonly int $version,
    public readonly int $expires,
    public readonly int $created,
    public readonly int $changed,
  ) {}

  /**
   * Whether this lease is still active at the supplied time.
   */
  public function isActive(int $now): bool {
    return $this->owner > 0 && $this->expires > $now;
  }

}
