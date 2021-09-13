<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for environment components.
 *
 * @package Drupal\exec_environment\Plugin\ExecEnvironment\Component
 */
abstract class ComponentBase extends PluginBase implements ComponentInterface {

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    return $this->configuration['priority'] ?? ($this->pluginDefinition['priority'] ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

}
