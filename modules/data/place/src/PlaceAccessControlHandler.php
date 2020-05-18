<?php

namespace Drupal\place;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Class PlaceAccessControlHandler
 *
 * @package Drupal\place
 */
class PlaceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $result = parent::checkAccess($entity, $operation, $account);

    // If access has already been forbidden the return it.
    if ($result->isForbidden()) {
      return $result;
    }

    // WE used the word "edit" for our permissions.
    if ($operation == 'update') {
      $operation = 'edit';
    }

    if (in_array($operation, ['edit', 'delete'])) {
      $bundle = $entity->bundle();
      $result = $result->orIf(
        AccessResult::allowedIfHasPermission($account, "{$operation} any {$bundle} places")
      );
      $result = $result->orIf(
        AccessResult::allowedIfHasPermission($account, "{$operation} own {$bundle} places")
          ->andIf(AccessResult::allowedIf($entity->owner->target_id == $account->id()))
      );
      $result->cachePerUser();
      $result->addCacheableDependency($entity);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);

    $create_permission = "create {$entity_bundle} places";
    $result = $result->orIf(
      AccessResult::allowedIfHasPermission($account, $create_permission)->cachePerPermissions()
    );

    return $result;
  }

}
