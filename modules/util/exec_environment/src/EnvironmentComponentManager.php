<?php

namespace Drupal\exec_environment;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\exec_environment\Annotation\ExecEnvironmentComponent;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface;

/**
 * Plugin manager for environment components.
 *
 * @method \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentInterface createInstance($plugin_id, array $configuration = [])
 */
class EnvironmentComponentManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ExecEnvironment/Component',
      $namespaces,
      $module_handler,
      ComponentInterface::class,
      ExecEnvironmentComponent::class
    );

    $this->alterInfo('exec_environment_component_info');
    $this->setCacheBackend($cache_backend, 'exec_environment_component_info');
  }

}
