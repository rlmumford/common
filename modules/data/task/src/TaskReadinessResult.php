<?php

namespace Drupal\task;

/**
 * A readiness decision at one point in time.
 */
class TaskReadinessResult {

  /**
   * Constructs the result with a primary state and all gating reasons.
   *
   * @param string $state
   *   Active, pending, blocked, resolved, or closed.
   * @param array $reasons
   *   Reasons containing a code and, where applicable, a target ID or status.
   */
  public function __construct(
    public readonly string $state,
    public readonly array $reasons,
  ) {}

}
