<?php

namespace Drupal\checklist\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for checklist item entities.
 */
class ChecklistItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!in_array($operation, ['view action state', 'execute action operation'], TRUE)) {
      return parent::checkAccess($entity, $operation, $account);
    }
    $reference = $entity->get('checklist');
    $host = $reference->entity;
    [$field_name] = explode(':', $reference->checklist_key ?? '', 2);
    if (!$host instanceof FieldableEntityInterface || !$host->hasField($field_name)) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }
    $field = $host->get($field_name);
    if ($field->getFieldDefinition()->getType() !== 'checklist') {
      return AccessResult::forbidden()->addCacheableDependency($host);
    }
    $access = $host->access($host->isNew() ? 'create' : 'view', $account, TRUE)
      ->andIf($field->access('view', $account, TRUE));
    if ($operation === 'execute action operation') {
      $access = $access->andIf($host->access($host->isNew() ? 'create' : 'update', $account, TRUE))
        ->andIf($field->access('edit', $account, TRUE));
    }
    // An item-level grant must not bypass missing host or field permission.
    if (!$access->isAllowed()) {
      $access = AccessResult::forbidden()->addCacheableDependency($access);
    }
    return $access->addCacheableDependency($host)->addCacheableDependency($entity);
  }

}
