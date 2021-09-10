<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface for components the can set the current user.
 *
 * @package Drupal\exec_environment\Plugin\ExecEnvironment\Component
 */
interface CurrentUserComponentInterface extends ComponentInterface {

  /**
   * Get the current user this component wants to set.
   *
   * This method is responsible for checking the current users access to switch
   * user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user to set.
   */
  public function getTargetCurrentUser() :? AccountInterface;

}
