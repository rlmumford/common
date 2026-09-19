<?php

namespace Drupal\checklist\Workspace;

/**
 * Durable ownership storage for checklist interaction workspaces.
 */
interface ChecklistWorkspaceStorageInterface {

  /**
   * Acquires a new fencing generation for a workspace.
   *
   * @throws \Drupal\checklist\Attempt\ChecklistAttemptConflictException
   *   If another user owns an unexpired lease.
   */
  public function acquire(ChecklistWorkspaceAddress $address, int $owner, int $lease_seconds = 300): ChecklistWorkspaceLease;

  /**
   * Renews an unexpired lease without changing its fencing generation.
   */
  public function renew(ChecklistWorkspaceLease $lease, int $lease_seconds = 300): ChecklistWorkspaceLease;

  /**
   * Releases a lease if its owner and generation are still current.
   */
  public function release(ChecklistWorkspaceLease $lease): void;

  /**
   * Loads the current workspace record, including an expired lease.
   */
  public function load(ChecklistWorkspaceAddress $address): ?ChecklistWorkspaceLease;

  /**
   * Checks whether a lease can still fence a mutation.
   */
  public function isCurrent(ChecklistWorkspaceLease $lease): bool;

}
