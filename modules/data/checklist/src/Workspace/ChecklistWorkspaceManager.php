<?php

namespace Drupal\checklist\Workspace;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Coordinates checklist workspace addresses and ownership operations.
 *
 * Adapters should use this facade so host/field/delta addressing is consistent
 * across UI, API and AI callers. It does not grant entity or field access.
 */
final class ChecklistWorkspaceManager {

  public function __construct(
    protected ChecklistWorkspaceStorageInterface $storage,
  ) {}

  /**
   * Builds the stable address for a checklist field on a host entity.
   */
  public function address(FieldableEntityInterface $entity, string $field_name, int $delta, string $checklist_key): ChecklistWorkspaceAddress {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->getFieldDefinition()->getType() !== 'checklist') {
      throw new \InvalidArgumentException('The workspace field must be a checklist field on the host entity.');
    }
    return ChecklistWorkspaceAddress::fromEntity($entity, $field_name, $delta, $checklist_key);
  }

  /**
   * Acquires a workspace lease for an explicitly addressed checklist.
   */
  public function acquire(ChecklistWorkspaceAddress $address, int $owner, int $lease_seconds = 300): ChecklistWorkspaceLease {
    return $this->storage->acquire($address, $owner, $lease_seconds);
  }

  /**
   * Renews a current workspace lease.
   */
  public function renew(ChecklistWorkspaceLease $lease, int $lease_seconds = 300): ChecklistWorkspaceLease {
    return $this->storage->renew($lease, $lease_seconds);
  }

  /**
   * Releases a current workspace lease.
   */
  public function release(ChecklistWorkspaceLease $lease): void {
    $this->storage->release($lease);
  }

  /**
   * Advances a workspace version under its current lease.
   */
  public function advanceVersion(ChecklistWorkspaceLease $lease, int $expected_version): ChecklistWorkspaceLease {
    return $this->storage->advanceVersion($lease, $expected_version);
  }

  /**
   * Checks whether a lease may still fence a mutation.
   */
  public function isCurrent(ChecklistWorkspaceLease $lease): bool {
    return $this->storage->isCurrent($lease);
  }

}
