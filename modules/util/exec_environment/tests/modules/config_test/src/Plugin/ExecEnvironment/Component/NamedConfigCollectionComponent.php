<?php

namespace Drupal\exec_environment_config_test\Plugin\ExecEnvironment\Component;

use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ComponentBase;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface;

/**
 * Component for a named config collection.
 *
 * @ExecEnvironmentComponent(
 *   id = "test_named_config_collection"
 * )
 */
class NamedConfigCollectionComponent extends ComponentBase implements ConfigFactoryCollectionComponentInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigCollectionName(): string {
    return $this->configuration['collection'] ?? 'test';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigCacheKeys(): array {
    return ['env__' . $this->getConfigCollectionName()];
  }

}
