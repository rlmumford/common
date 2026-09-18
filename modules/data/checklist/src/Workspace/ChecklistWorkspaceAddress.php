<?php

namespace Drupal\checklist\Workspace;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Stable address for a checklist interaction workspace.
 */
final class ChecklistWorkspaceAddress {

  public function __construct(
    public readonly string $hostType,
    public readonly string $hostUuid,
    public readonly string $fieldName,
    public readonly int $delta,
    public readonly string $checklistKey,
  ) {
    if ($hostType === '' || $hostUuid === '' || $fieldName === '' || $delta < 0 || $checklistKey === '') {
      throw new \InvalidArgumentException('A checklist workspace address must identify a host, field, delta and key.');
    }
  }

  /**
   * Creates an address from a host entity and checklist field location.
   */
  public static function fromEntity(FieldableEntityInterface $entity, string $field_name, int $delta, string $checklist_key): self {
    return new self($entity->getEntityTypeId(), $entity->uuid(), $field_name, $delta, $checklist_key);
  }

  /**
   * Returns a collision-resistant database key for this address.
   */
  public function id(): string {
    return hash('sha256', implode("\0", [$this->hostType, $this->hostUuid, $this->fieldName, $this->delta, $this->checklistKey]));
  }

}
