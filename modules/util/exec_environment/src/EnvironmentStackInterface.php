<?php

namespace Drupal\exec_environment;

/**
 * Interface for the Environment Stack.
 *
 * The environment stack service is used to apply and remove execution
 * environments. It can also be used to get the current environment from the
 * stack.
 *
 * @package Drupal\exec_environment
 */
interface EnvironmentStackInterface {

  /**
   * Apply a new environment on top of the current one.
   *
   * @param \Drupal\exec_environment\EnvironmentInterface $environment
   *   The environment to apply.
   */
  public function applyEnvironment(EnvironmentInterface $environment);

  /**
   * Disapply the current environment and reset any changes to the previous.
   */
  public function resetEnvironment();

  /**
   * Get the currently active environment.
   *
   * @return \Drupal\exec_environment\EnvironmentInterface
   *   The currently active environment.
   */
  public function getActiveEnvironment() : EnvironmentInterface;

}
