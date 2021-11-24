<?php

namespace Drupal\exec_environment_config_test\Plugin\ExecEnvironment\Component;

use Drupal\exec_environment\Plugin\ExecEnvironment\Component\CacheBinSuffixComponentInterface;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentBase;

/**
 * Component plugin to name a cache bin suffix.
 *
 * @ExecEnvironmentComponent(
 *   id = "test_named_cache_bin_suffix"
 * )
 */
class NamedCacheBinSuffixComponent extends ComponentBase implements CacheBinSuffixComponentInterface {

  /**
   * {@inheritdoc}
   */
  public function getCacheBinSuffixPart(string $bin): ?string {
    return $this->configuration['name'] ?? 'test';
  }

}
