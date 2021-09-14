<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

/**
 * Interface for components that can set the config environment.
 */
interface ConfigFactoryCollectionComponentInterface extends ComponentInterface {

  /**
   * Get the config factory collection name.
   *
   * @param string[] &$names_or_prefixes
   *   The names or prefixes of the config being loaded. If the collection does
   *   not support one of these, it should be removed from the array.
   *
   * @return string|null
   *   The collection name or null if this component does not provide a
   *   collection.
   */
  public function getConfigCollectionName(array &$names_or_prefixes) : ?string;

  /**
   * Get the config cache keys.
   *
   * @param string $name
   *   The name of the config to get cache keys for.
   *
   * @return string[]
   *   The config cache keys.
   */
  public function getConfigCacheKeys(string $name) : array;

}
