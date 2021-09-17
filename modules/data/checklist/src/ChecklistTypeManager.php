<?php

namespace Drupal\checklist;

use Drupal\checklist\Annotation\ChecklistType;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Class ChecklistTypeManager.
 *
 * The checklist type determines the bundle of the checklist item. This means
 * that you end up with at least one bundle per entity type that can have a
 * checklist on it.
 *
 * @package Drupal\checklist
 */
class ChecklistTypeManager extends DefaultPluginManager {

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
      'Plugin/ChecklistType',
      $namespaces,
      $module_handler,
      ChecklistTypeInterface::class,
      ChecklistType::class
    );

    $this->alterInfo('checklist_type_info');
    $this->setCacheBackend($cache_backend, 'checklist_type_info');
  }

}
