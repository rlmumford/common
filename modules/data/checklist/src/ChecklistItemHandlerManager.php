<?php

namespace Drupal\checklist;

use Drupal\checklist\Annotation\ChecklistItemHandler;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for checklist item handlers.
 */
class ChecklistItemHandlerManager extends DefaultPluginManager {

  /**
   * Constructs a new ChecklistTypeManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ChecklistItemHandler',
      $namespaces,
      $module_handler,
      ChecklistItemHandlerInterface::class,
      ChecklistItemHandler::class
    );

    $this->alterInfo('checklist_item_handler_info');
    $this->setCacheBackend($cache_backend, 'checklist_item_handler_info');
  }

}
