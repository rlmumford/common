<?php

namespace Drupal\exec_environment\Cache;

use Drupal\Core\Cache\CacheFactory;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\CacheBinSuffixComponentInterface;

/**
 * Cache factory that makes environment aware cache bins.
 *
 * @package Drupal\exec_environment\Cache
 */
class EnvironmentAwareCacheFactory extends CacheFactory {

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    // Config is handled by the config manager. Bootstrap is too early.
    if (in_array($bin, ['config', 'bootstrap', 'discovery_noenv'])) {
      return parent::get($bin);
    }

    $suffixes = [];
    /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\CacheBinSuffixComponentInterface $component */
    foreach ($this->container->get('environment_stack')->getActiveEnvironment()->getComponents(CacheBinSuffixComponentInterface::class) as $component) {
      if ($suffix = $component->getCacheBinSuffixPart($bin)) {
        $suffixes[] = $suffix;
      }
    }

    if (!empty($suffixes)) {
      sort($suffixes);
      $bin .= ".exenv." . implode(".", $suffixes);
    }

    return parent::get($bin);
  }

}
