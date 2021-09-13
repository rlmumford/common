<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\exec_environment\EnvironmentInterface;

/**
 * Interface for impact applicators.
 */
interface ImpactApplicatorInterface extends PluginInspectionInterface {

  /**
   * Apply this impact of the environment.
   *
   * @param \Drupal\exec_environment\EnvironmentInterface $environment
   *   The environment to apply.
   */
  public function apply(EnvironmentInterface $environment);

  /**
   * Reset the impact on the environment.
   *
   * @param \Drupal\exec_environment\EnvironmentInterface $environment
   *   The environment being disapplied.
   */
  public function reset(EnvironmentInterface $environment);

}
