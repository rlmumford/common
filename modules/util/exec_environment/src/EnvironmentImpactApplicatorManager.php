<?php

namespace Drupal\exec_environment;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\exec_environment\Annotation\ExecEnvironmentImpactApplicator;
use Drupal\exec_environment\Plugin\ExecEnvironment\ImpactApplicator\ImpactApplicatorInterface;

/**
 * Plugin manager for environment impact plugins.
 *
 * Applicator plugins apply environment components to the system.
 */
class EnvironmentImpactApplicatorManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ExecEnvironment/ImpactApplicator',
      $namespaces,
      $module_handler,
      ImpactApplicatorInterface::class,
      ExecEnvironmentImpactApplicator::class
    );

    $this->alterInfo('exec_environment_impact_applicator_info');
    $this->setCacheBackend($cache_backend, 'exec_environment_impact_applicator_info');
  }

}
