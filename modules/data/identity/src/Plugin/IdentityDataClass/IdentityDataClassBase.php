<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataStorage;
use Drupal\identity\Entity\IdentityStorage;
use Drupal\identity\IdentityMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IdentityDataClassBase extends PluginBase implements IdentityDataClassInterface, ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\identity\Entity\IdentityStorage
   */
  protected $identityStorage;

  /**
   * @var \Drupal\identity\Entity\IdentityDataStorage
   */
  protected $identityDataStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('identity'),
      $container->get('entity_type.manager')->getStorage('identity_data')
    );
  }

  /**
   * IdentityDataClassBase constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\identity\Entity\IdentityStorage $identity_storage
   * @param \Drupal\identity\Entity\IdentityDataStorage $identity_data_storage
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, IdentityStorage $identity_storage, IdentityDataStorage $identity_data_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->identityStorage = $identity_storage;
    $this->identityDataStorage = $identity_data_storage;
  }

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

  /**
   * {@inheritdoc}
   */
  public function acquisitionPriority(IdentityData $data) {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function supportOrOppose(IdentityData $data, IdentityMatch $match) {
  }

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [];
  }
}
