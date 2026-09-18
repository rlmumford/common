<?php

namespace Drupal\checklist;

use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves checklist fields on the entity supplied by the caller.
 */
class ChecklistResolver {

  /**
   * Constructs the checklist resolver.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current account.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Resolves an accessible checklist on a saved or unsaved entity.
   *
   * Preserves the supplied entity, translation, revision and checklist state.
   * The caller owns entity loading and workspace selection. This method does
   * not reload, merge tempstore, save anything or acquire an editing lock.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The host entity, including any current unsaved edits.
   * @param string $field_name
   *   The checklist field name.
   * @param int $delta
   *   The field delta, including zero for a single-value field.
   * @param string $operation
   *   Either view or update. New hosts require create access; update also
   *   requires field edit access.
   *
   * @return \Drupal\checklist\ChecklistInterface
   *   The checklist on the supplied entity.
   *
   * @throws \InvalidArgumentException
   *   When the operation is unsupported or an attached graph has another host.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the address does not identify a checklist for this host type.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When host or field access is denied.
   */
  public function resolve(FieldableEntityInterface $entity, string $field_name, int $delta = 0, string $operation = 'view'): ChecklistInterface {
    if (!in_array($operation, ['view', 'update'], TRUE)) {
      throw new \InvalidArgumentException('Checklist resolution supports view or update access.');
    }
    if ($delta < 0) {
      throw new NotFoundHttpException('Checklist not found.');
    }
    $allowed = $entity->isNew()
      ? $entity->access('create', $this->currentUser)
      : $entity->access('view', $this->currentUser) && ($operation === 'view' || $entity->access('update', $this->currentUser));
    if (!$allowed) {
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
    if (!$type instanceof ChecklistTypeInterface || $type->getPluginDefinition()['entity_type'] !== $entity->getEntityTypeId()) {
      throw new NotFoundHttpException('Checklist type does not match its host.');
    }
    $checklist = $item->get('checklist')->getLocalValue();
    if ($checklist->getEntity() !== $entity) {
      throw new \InvalidArgumentException('The attached checklist belongs to another entity instance; supply its host entity.');
    }
    return $checklist;
  }

}
