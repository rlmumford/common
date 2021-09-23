<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for Environment Components.
 *
 * Components can be created very, very early in the bootstrap cycle, especially
 * if created when detecting the default environment. Service that depend on the
 * config.factory service should not be injected as this will often lead to a
 * circular reference when instantiating the environment stack.
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
