<?php

namespace Drupal\project\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for project entities.
 */
class ProjectAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\project\Entity\ProjectInterface $entity */
    $result = parent::checkAccess($entity, $operation, $account);
    $result->addCacheableDependency($entity);

    return $result->orIf(AccessResult::allowedIfHasPermission($account, $operation.' any project'))
      ->orIf(
        AccessResult::allowedIfHasPermission($account, $operation.' any managed project')
          ->andIf(AccessResult::allowedIf($account->id() == $entity->getManagerId()))
      );
  }
}
