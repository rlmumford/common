<?php

namespace Drupal\exec_environment\Cache;

use Drupal\Core\Cache\CacheFactory;

/**
 * Cache factory that makes environment aware cache bins.
 *
 * Plugin managers are known to static cache their definitions on their
 * definitions property. I'm not sure how to work around that.
 *
 * @package Drupal\exec_environment\Cache
 */
class EnvironmentAwareCacheFactory extends CacheFactory {

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $backend = parent::get($bin);

    // Config is handled by the config manager. Bootstrap is too early.
    if (in_array($bin, ['config', 'bootstrap', 'discovery_noenv'])) {
      return $backend;
    }

    return new EnvironmentCacheBackendWrapper($this->container->get('environment_stack'), $backend, $bin);
  }

}
