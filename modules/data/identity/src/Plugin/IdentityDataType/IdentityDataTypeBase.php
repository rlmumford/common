<?php

namespace Drupal\identity\Plugin\IdentityDataType;

use Drupal\Core\Plugin\PluginBase;

class IdentityDataTypeBase extends PluginBase implements IdentityDataTypeInterface {

  /**
   * Builds the field definitions for entities of this bundle.
   *
   * Important:
   * Field names must be unique across all bundles.
   * It is recommended to prefix them with the bundle name (plugin ID).
   *
   * @return \Drupal\entity\BundleFieldDefinition[]
   *   An array of bundle field definitions, keyed by field name.
   */
  public function buildFieldDefinitions() {
    $fields = [];
    return $fields;
  }
}
