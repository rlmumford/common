<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

interface ConfigFactoryCollectionComponentInterface extends ComponentInterface {

  /**
   * Get the config factory collection name.
   *
   * @return string
   *   The collection name.
   */
  public function getConfigCollectionName() : string;

  /**
   * Get the config cache keys.
   *
   * @return string[]
   *   The config cache keys.
   */
  public function getConfigCacheKeys() : array;

}
