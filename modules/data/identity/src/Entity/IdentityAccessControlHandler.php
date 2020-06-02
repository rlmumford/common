<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class IdentityAccessControlHandler
 *
 * @package Drupal\identity\Entity
 */
class IdentityAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type, $container->get('entity_type.manager'));
  }

  /**
   * IdentityAccessControlHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * In short you have access to an identity if you have access to some data to
   * do with that identity.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = parent::checkAccess($entity, $operation, $account);
    $access = $access->orIf(AccessResult::allowedIfHasPermission($account, "{$operation} any identity data"));

    // We only want to do the query thing if they don't have access already, to
    // save time
    if (!$access->isAllowed()) {
      $query = $this->entityTypeManager->getStorage('identity_data')->getQuery();
      $query->condition('identity', $entity->id());
      $query->addTag('identity_data_access');
      $query->addMetaData('account', $account);
      $query->addMetaData('op', $operation);
      $query->range(0, 1);

      $access = $access->orIf(
        AccessResult::allowedIf($query->count()->execute())->addCacheableDependency($entity)
      );
    }

    return $access;
  }
}
