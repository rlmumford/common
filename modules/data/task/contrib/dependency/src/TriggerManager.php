<?php

namespace Drupal\task_dependency;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\task_dependency\Annotation\DependencyTrigger;

/**
 * Discovers reusable event matchers without depending on task-job creation.
 */
class TriggerManager extends DefaultPluginManager {

  /**
   * Constructs the trigger manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache, ModuleHandlerInterface $modules) {
    parent::__construct('Plugin/DependencyTrigger', $namespaces, $modules, TriggerInterface::class, DependencyTrigger::class);
    $this->alterInfo('task_dependency_trigger_info');
    $this->setCacheBackend($cache, 'task_dependency_trigger_info');
  }

}
