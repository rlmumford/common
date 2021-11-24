<?php

namespace Drupal\exec_environment\Plugin\ExecEnvironment\Component;

/**
 * Interface for environment components that alter the cache bin suffix.
 *
 * @package Drupal\exec_environment\Plugin\ExecEnvironment\Component
 */
interface CacheBinSuffixComponentInterface extends ComponentInterface {

  /**
   * Get the suffix part.
   *
   * @param string $bin
   *   The bin to consider adding a suffix to.
   *
   * @return string|null
   *   A string to be appended to the end of the cache bin.
   */
  public function getCacheBinSuffixPart(string $bin) :? string;

}
