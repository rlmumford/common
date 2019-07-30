<?php

namespace Drupal\identity;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\identity\Annotation\IdentityDataType;
use Drupal\identity\Plugin\IdentityDataType\IdentityDataTypeInterface;

class IdentityDataTypeManager extends DefaultPluginManager {

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
      'Plugin/IdentityDataType',
      $namespaces,
      $module_handler,
      IdentityDataTypeInterface::class,
      IdentityDataType::class
    );

    $this->alterInfo('identity_data_type_info');
    $this->setCacheBackend($cache_backend, 'identity_data_type_info');
  }
}
