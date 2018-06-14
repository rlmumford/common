<?php

namespace Drupal\job_role;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for job role entity storage.
 */
interface JobRoleStorageInterface extends EntityStorageInterface {

  /**
   * Loads the given user's job_role.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *    The user entity.
   * @param bool $active
   *    Boolean representing if job_role active or not.
   *
   * @return \Drupal\job_role\Entity\JobRoleInterface
   *    The loaded sowlo_role entity.
   */
  public function loadByUser(AccountInterface $account, $active);

  /**
   * Loads the given user's sowlo_roles.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *    The user entity.
   * @param bool $active
   *    Boolean representing if sowlo_role active or not.
   *
   * @return \Drupal\job_role\Entity\JobRoleInterface[]
   *    An array of loaded sowlo_role entities.
   */
  public function loadMultipleByUser(AccountInterface $account, $active);

}
