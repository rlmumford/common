<?php
/**
 * Created by PhpStorm.
 * User: Mumford
 * Date: 12/10/2018
 * Time: 12:27
 */

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\layout_builder\SectionStorageInterface;

interface DisplayWideConfigSectionStorageInterface extends SectionStorageInterface {

  /**
   * Get the display wide configuration for a given key.
   * 
   * @param string $key
   *
   * @return mixed
   */
  public function getConfig($key = '');
  
  /**
   * Set the display wide configuration for a given key.
   */
  public function setConfig($key, $config);
  
}
