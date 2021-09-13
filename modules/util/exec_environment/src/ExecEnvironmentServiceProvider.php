<?php

namespace Drupal\exec_environment;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
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
  }

}
