<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class IdentityDataAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = parent::checkAccess($entity, $operation, $account);

    $operation = ($operation === 'edit') ? 'update' : $operation;

    /** @var \Drupal\identity\Entity\IdentityData $entity */
    $access = $access->orIf(AccessResult::allowedIfHasPermission($account, "{$operation} any identity data"));
    $access = $access->orIf(
      AccessResult::allowedIfHasPermission($account, "{$operation} own identity data")
        ->andIf(
          AccessResult::allowedIf($account->id() === $entity->getOwnerId())
            ->addCacheableDependency($account)
            ->addCacheableDependency($entity)
        )
    );

    return $access;
  }

}
