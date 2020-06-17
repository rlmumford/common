<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextAwarePluginManagerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\task_job\Annotation\JobTrigger;

class JobTriggerManager extends DefaultPluginManager {
  use ContextAwarePluginManagerTrait;

  /**
   * JobTriggerManager constructor.
   *
   * @param \Traversable $namespaces
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/JobTrigger',
      $namespaces,
      $module_handler,
      JobTriggerInterface::class,
      JobTrigger::class
    );

    $this->alterInfo('job_trigger_info');
    $this->setCacheBackend($cache_backend, 'job_trigger_info');
  }
}
