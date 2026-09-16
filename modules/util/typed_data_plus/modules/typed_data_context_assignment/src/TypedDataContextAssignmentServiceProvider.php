<?php

namespace Drupal\typed_data_context_assignment;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\typed_data_context_assignment\Plugin\Context\ContextHandler;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider for the typed data service provider module.
 *
 * @package Drupal\typed_data_context_assignment
 */
class TypedDataContextAssignmentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('context.handler')
      ->setClass(ContextHandler::class)
      ->setArguments([
        new Reference('typed_data.data_fetcher'),
        new Reference('typed_data_manager'),
      ]);
  }

}
