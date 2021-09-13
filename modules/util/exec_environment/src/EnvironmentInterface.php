<?php

namespace Drupal\exec_environment;

use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface;

/**
 * Holds information about an execution environment.
 *
 * @package Drupal\exec_environment
 */
interface EnvironmentInterface {

  /**
   * Get the environment this was laid on top of.
   *
   * @return \Drupal\exec_environment\EnvironmentInterface
   *   The previous environment.
   */
  public function previousEnvironment() :? EnvironmentInterface;

  /**
   * Set the previous environment.
   *
   * @param \Drupal\exec_environment\EnvironmentInterface $environment
   *   The previous environment.
   *
   * @return $this
   */
  public function setPreviousEnvironment(EnvironmentInterface $environment) : EnvironmentInterface;

  /**
   * Add a component to this environment.
   *
   * @param \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface $component
   *   The component to add.
   *
   * @return $this
   */
  public function addComponent(ComponentInterface $component) : EnvironmentInterface;

  /**
   * Get all the components in this environment.
   *
   * @param string|null $impact_interface
   *   If you only want components that implement a specific interface, specify
   *   the interface.
   *
   * @return \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface[]
   *   The relevant components.
   */
  public function getComponents(string $impact_interface = NULL) : array;

}
