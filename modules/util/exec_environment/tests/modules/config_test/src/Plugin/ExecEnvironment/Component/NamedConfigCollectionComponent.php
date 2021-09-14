<?php

namespace Drupal\exec_environment_config_test\Plugin\ExecEnvironment\Component;

use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentBase;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentBase;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface;

/**
 * Component for a named config collection.
 *
 * @ExecEnvironmentComponent(
 *   id = "test_named_config_collection"
 * )
 */
class NamedConfigCollectionComponent extends ConfigFactoryCollectionComponentBase implements ConfigFactoryCollectionComponentInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigCollectionName(array &$names_or_prefixes): ?string {
    $names_or_prefixes = array_filter($names_or_prefixes, [$this, 'appliesToNameOrPrefix']);
    return !empty($names_or_prefixes) ? ($this->configuration['collection'] ?? 'test') : NULL;
  }

}
