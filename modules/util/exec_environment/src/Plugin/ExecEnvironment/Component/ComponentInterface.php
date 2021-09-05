<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for Environment Components.
 */
interface ComponentInterface extends PluginInspectionInterface, ConfigurableInterface {

  /**
   * Get the priority of this component.
   *
   * @return int
   *   The priority.
   */
  public function getPriority() : int;

}
