<?php

namespace Drupal\checklist;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

class ChecklistServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['message'])) {
      $definition = new Definition(
        '\Drupal\checklist\EventSubscriber\ChecklistItemEventMessageSubscriber'
      );
      $definition->addTag('event_subscriber');
      $container->setDefinition(
        'checklist.checklist_item_event_message_subscriber',
        $definition
      );
    }
  }

}
