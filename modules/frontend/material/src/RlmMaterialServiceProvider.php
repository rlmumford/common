<?php

namespace Drupal\rlm_material;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

class RlmMaterialServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['social_media'])) {
      $definition = new Definition(
        '\Drupal\rlm_material\EventSubscriber\SocialMediaEventSubscriber'
      );
      $definition->addTag('event_subscriber');
      $container->setDefinition(
        'rlm_material.social_media_subscriber',
        $definition
      );
    }
  }

}
