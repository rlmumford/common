<?php

namespace Drupal\identity;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\identity\Annotation\IdentityDataClass;
use Drupal\identity\Plugin\IdentityDataClass\IdentityDataClassInterface;

class IdentityDataClassManager extends DefaultPluginManager {

  /**
   * Constructs a new IdentityTypeManager object.
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
      'Plugin/IdentityDataClass',
      $namespaces,
      $module_handler,
      IdentityDataClassInterface::class,
      IdentityDataClass::class
    );

    $this->alterInfo('identity_data_class_info');
    $this->setCacheBackend($cache_backend, 'identity_data_class_info');
  }
}
