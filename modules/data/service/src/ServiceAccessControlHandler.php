<?php

namespace Drupal\service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for service entities.
 */
class ServiceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\service\ServiceInterface $entity */
    $result = parent::checkAccess($entity, $operation, $account);
    $result->addCacheableDependency($entity);

    return $result->orIf(AccessResult::allowedIfHasPermission($account, $operation.' any service'))
      ->orIf(
        AccessResult::allowedIfHasPermission($account, $operation.' any managed service')
          ->andIf(AccessResult::allowedIf($account->id() == $entity->getManagerId()))
      )
      ->orIf(
        AccessResult::allowedIfHasPermission($account, $operation.' any received service')
          ->andIf(AccessResult::allowedIf(in_array($account->id(), $entity->getRecipientIds())))
      );
  }
}
