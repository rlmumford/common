<?php

namespace Drupal\checklist;

use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves persisted checklist fields without substituting form tempstore.
 */
class ChecklistResolver {

  /**
   * Constructs the checklist resolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current account.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Loads an accessible persisted checklist at a field location.
   *
   * Loads the default revision and default translation. This is the persisted
   * base for a future workspace resolver, not authority to bypass ownership or
   * version checks. It does not merge tempstore or acquire an editing lock.
   *
   * @param string $entity_type
   *   The host entity type.
   * @param string $entity_id
   *   The host entity ID.
   * @param string $field_name
   *   The checklist field name.
   * @param int $delta
   *   The field delta, including zero for a single-value field.
   * @param string $operation
   *   Either view or update. Update also requires field edit access.
   *
   * @return \Drupal\checklist\ChecklistInterface
   *   The resolved persisted checklist.
   *
   * @throws \InvalidArgumentException
   *   When the operation is unsupported.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the address does not identify a checklist for this host type.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When host or field access is denied.
   */
  public function resolveStored(string $entity_type, string $entity_id, string $field_name, int $delta = 0, string $operation = 'view'): ChecklistInterface {
    if (!in_array($operation, ['view', 'update'], TRUE)) {
      throw new \InvalidArgumentException('Checklist resolution supports view or update access.');
    }
    if ($delta < 0 || !$this->entityTypeManager->hasDefinition($entity_type)) {
      throw new NotFoundHttpException('Checklist not found.');
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->loadUnchanged($entity_id);
    if (!$entity instanceof FieldableEntityInterface) {
      throw new NotFoundHttpException('Checklist not found.');
    }
    $entity = $entity->getUntranslated();
    if (!$entity->access('view', $this->currentUser) || ($operation === 'update' && !$entity->access('update', $this->currentUser))) {
      throw new AccessDeniedHttpException('The checklist host is not accessible.');
    }
    if (!$entity->hasField($field_name)) {
      throw new NotFoundHttpException('Checklist not found.');
    }
    $field = $entity->get($field_name);
    if ($field->getFieldDefinition()->getType() !== 'checklist') {
      throw new NotFoundHttpException('Checklist not found.');
    }
    if (!$field->access('view', $this->currentUser) || ($operation === 'update' && !$field->access('edit', $this->currentUser))) {
      throw new AccessDeniedHttpException('The checklist field is not accessible.');
    }
    $item = $field->get($delta);
    if (!$item || $item->isEmpty()) {
      throw new NotFoundHttpException('Checklist not found.');
    }
    $type = $item->plugin;
    if (!$type instanceof ChecklistTypeInterface || $type->getPluginDefinition()['entity_type'] !== $entity_type) {
      throw new NotFoundHttpException('Checklist type does not match its host.');
    }
    $key = $field_name;
    if ($field->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() !== 1) {
      $key .= ':' . $delta;
    }
    // Explicit persisted resolution must not reuse mutated item entity objects.
    $type->itemStorage()->resetCache();
    $checklist = $type->getChecklist($entity, $key);
    // Item references must return this same graph, not the tempstore adapter.
    $item->get('checklist')->setValue($checklist, FALSE);
    return $checklist;
  }

}
