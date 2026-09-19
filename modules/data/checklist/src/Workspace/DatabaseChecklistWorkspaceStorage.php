<?php

namespace Drupal\checklist\Workspace;

use Drupal\checklist\Attempt\ChecklistAttemptConflictException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * SQL-backed checklist workspace ownership storage.
 */
final class DatabaseChecklistWorkspaceStorage implements ChecklistWorkspaceStorageInterface {

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function acquire(ChecklistWorkspaceAddress $address, int $owner, int $lease_seconds = 300): ChecklistWorkspaceLease {
    $this->validate($owner, $lease_seconds);
    $now = $this->time->getCurrentTime();
    $transaction = $this->database->startTransaction();
    try {
      $current = $this->loadRow($address, TRUE);
      if ($current && $current->isActive($now) && $current->owner !== $owner) {
        throw new ChecklistAttemptConflictException('The checklist workspace is owned by another user.');
      }
      $generation = $current ? $current->generation + 1 : 1;
      $fields = [
        'host_type' => $address->hostType,
        'host_uuid' => $address->hostUuid,
        'field_name' => $address->fieldName,
        'delta' => $address->delta,
        'checklist_key' => $address->checklistKey,
        'owner' => $owner,
        'generation' => $generation,
        'version' => $current ? $current->version : 0,
        'expires' => $now + $lease_seconds,
        'changed' => $now,
      ];
      if ($current) {
        $this->database->update('checklist_workspace')->fields($fields)->condition('id', $address->id())->execute();
      }
      else {
        $this->database->insert('checklist_workspace')->fields(['id' => $address->id(), 'created' => $now] + $fields)->execute();
      }
      return new ChecklistWorkspaceLease($address, $owner, $generation, (int) $fields['version'], (int) $fields['expires'], $current?->created ?? $now, $now);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function renew(ChecklistWorkspaceLease $lease, int $lease_seconds = 300): ChecklistWorkspaceLease {
    $this->validate($lease->owner, $lease_seconds);
    $now = $this->time->getCurrentTime();
    $expires = $now + $lease_seconds;
    $updated = $this->database->update('checklist_workspace')->fields([
      'expires' => $expires,
      'changed' => $now,
    ])->condition('id', $lease->address->id())->condition('owner', $lease->owner)
      ->condition('generation', $lease->generation)->condition('expires', $now, '>')->execute();
    if (!$updated) {
      throw new ChecklistAttemptConflictException('The checklist workspace lease has expired or changed.');
    }
    return new ChecklistWorkspaceLease($lease->address, $lease->owner, $lease->generation, $lease->version, $expires, $lease->created, $now);
  }

  /**
   * {@inheritdoc}
   */
  public function release(ChecklistWorkspaceLease $lease): void {
    $this->database->update('checklist_workspace')->fields([
      'owner' => 0,
      'expires' => 0,
      'changed' => $this->time->getCurrentTime(),
    ])->condition('id', $lease->address->id())->condition('owner', $lease->owner)
      ->condition('generation', $lease->generation)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load(ChecklistWorkspaceAddress $address): ?ChecklistWorkspaceLease {
    return $this->loadRow($address, FALSE);
  }

  /**
   * Loads a workspace, optionally locking its row in the current transaction.
   */
  protected function loadRow(ChecklistWorkspaceAddress $address, bool $for_update): ?ChecklistWorkspaceLease {
    $query = $this->database->select('checklist_workspace', 'w')->fields('w');
    $query->condition('id', $address->id());
    if ($for_update) {
      $query->forUpdate();
    }
    $row = $query->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    return new ChecklistWorkspaceLease(
      $address,
      (int) $row['owner'],
      (int) $row['generation'],
      (int) $row['version'],
      (int) $row['expires'],
      (int) $row['created'],
      (int) $row['changed'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isCurrent(ChecklistWorkspaceLease $lease): bool {
    $now = $this->time->getCurrentTime();
    return (bool) $this->database->select('checklist_workspace', 'w')->fields('w', ['id'])
      ->condition('id', $lease->address->id())->condition('owner', $lease->owner)
      ->condition('generation', $lease->generation)->condition('expires', $now, '>')->range(0, 1)->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function advanceVersion(ChecklistWorkspaceLease $lease, int $expected_version): ChecklistWorkspaceLease {
    if ($expected_version < 0) {
      throw new \InvalidArgumentException('The expected workspace version cannot be negative.');
    }
    $now = $this->time->getCurrentTime();
    $updated = $this->database->update('checklist_workspace')->fields([
      'version' => $expected_version + 1,
      'changed' => $now,
    ])->condition('id', $lease->address->id())->condition('owner', $lease->owner)
      ->condition('generation', $lease->generation)->condition('version', $expected_version)
      ->condition('expires', $now, '>')->execute();
    if (!$updated) {
      throw new ChecklistAttemptConflictException('The checklist workspace version or lease has changed.');
    }
    return new ChecklistWorkspaceLease($lease->address, $lease->owner, $lease->generation, $expected_version + 1, $lease->expires, $lease->created, $now);
  }

  /**
   * Validates ownership and lease duration inputs.
   */
  protected function validate(int $owner, int $lease_seconds): void {
    if ($owner < 1) {
      throw new \InvalidArgumentException('A workspace owner must be an authenticated user.');
    }
    if ($lease_seconds < 1 || $lease_seconds > 86400) {
      throw new \InvalidArgumentException('The workspace lease must last between one second and one day.');
    }
  }

}
