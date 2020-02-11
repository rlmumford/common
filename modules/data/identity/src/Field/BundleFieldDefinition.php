<?php

namespace Drupal\identity\Field;

use Drupal\entity\BundleFieldDefinition as BaseBundleFieldDefinition;

/**
 * Class BundleFieldDefinition
 *
 * Override to allow indexes to be set as custom.
 *
 * @package Drupal\identity\Field
 */
class BundleFieldDefinition extends BaseBundleFieldDefinition {

  /**
   * @param array $indexes
   *
   * @return static
   */
  public function setIndexes(array $indexes) {
    $this->indexes = $indexes;
    return $this;
  }

}
