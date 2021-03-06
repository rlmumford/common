<?php

namespace Drupal\project;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

class ProjectServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['task'])) {
      $definition = new Definition(
        '\Drupal\project\EventSubscriber\TaskAssigneeSubscriber'
      );
      $definition->addTag('event_subscriber');
      $container->setDefinition(
        'project.task_assignee_subscriber',
        $definition
      );
    }
  }
}
