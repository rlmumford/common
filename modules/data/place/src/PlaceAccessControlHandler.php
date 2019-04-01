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

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);

    $create_permission = "create new {$entity_bundle} places";
    $result = $result->orIf(
      AccessResult::allowedIfHasPermission($account, $create_permission)->cachePerPermissions()
    );

    return $result;
  }

}
