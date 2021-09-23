<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

/**
 * Base class for config factory components.
 *
 * @package Drupal\exec_environment\Plugin\ExecEnvironment\Component
 */
abstract class ConfigFactoryCollectionComponentBase extends ComponentBase implements ConfigFactoryCollectionComponentInterface {

  /**
   * Config prefixes that should not be environmental.
   *
   * Some prefixes should not be environmental because the can effect the schema
   * or get populated too early in the bootstrap cycle for this to function
   * properly.
   *
   * @var string[]
   */
  protected $excludedConfigPrefixes = [
    'core.extension',
    'field.storage'
  ];

  /**
   * Determine whether the environment applies to the given name or prefix.
   *
   * @param string $name_or_prefix
   *   The name or prefix of the configuration.
   *
   * @return bool
   *   TRUE if it applies, FALSE otherwise.
   */
  protected function appliesToNameOrPrefix(string $name_or_prefix) : bool {
    foreach ($this->excludedConfigPrefixes as $prefix) {
      if (stripos($name_or_prefix, $prefix) === 0) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigCacheKeys(string $name): array {
    $names = [$name];
    return array_filter([
      $this->getConfigCollectionName($names),
    ]);
  }

}
