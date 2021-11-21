<?php

namespace Drupal\exec_environment;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\exec_environment\Cache\EnvironmentAwareCacheFactory;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Exec Environment service provider.
 *
 * @package Drupal\exec_environment
 */
class ExecEnvironmentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('config.factory')
      ->setClass(EnvironmentConfigFactory::class)
      ->addArgument(new Reference('environment_stack'));

    $container->getDefinition('cache_factory')
      ->setClass(EnvironmentAwareCacheFactory::class);

    $no_env_discovery = ['entity_type.manager'];
    foreach ($no_env_discovery as $service_id) {
      $definition = $container->getDefinition($service_id);

      $arguments = $definition->getArguments();
      foreach ($arguments as &$argument) {
        if (($argument instanceof Reference) && (string) $argument === 'cache.discovery') {
          $argument = new Reference('cache.discovery_noenv');
        }
      }
      $definition->setArguments($arguments);
    }

  }

}
