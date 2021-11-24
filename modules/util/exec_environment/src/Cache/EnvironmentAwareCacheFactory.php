<?php

namespace Drupal\exec_environment\Cache;

use Drupal\Core\Cache\CacheFactory;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\CacheBinSuffixComponentInterface;

/**
 * Cache factory that makes environment aware cache bins.
 *
 * This doesn't work when applying environments. It would be better to, instead
 * of changing the bin name, wrap the bin in a class that appends things to the
 * cids based on the environment stack. That way existing or persistent bins
 * will still return the correct cache items.
 *
 * Plugin managers are known to static cache their definitions on their
 * definitions property. I'm not sure how to work around that.
 *
 * @todo implement the change outlined above.
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
      $bin .= "__exenv_" . implode("_", $suffixes);
    }

    return parent::get($bin);
  }

}
