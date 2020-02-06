<?php

namespace Drupal\field_tools;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\field_tools\Normalizer\FieldNormalizer;

/**
 * Class FieldToolsServiceProvider
 *
 * @package Drupal\field_tools
 */
class FieldToolsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('serialization.normalizer.field')) {
      $definition = $container->getDefinition('serialization.normalizer.field');
      $definition->setClass(FieldNormalizer::class);
    }
  }

}
