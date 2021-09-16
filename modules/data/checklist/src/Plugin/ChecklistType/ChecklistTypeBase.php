<?php

namespace Drupal\checklist\Plugin\ChecklistType;

use Drupal\checklist\Checklist;
use Drupal\checklist\ChecklistInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ChecklistTypeBase extends PluginBase implements ChecklistTypeInterface, ContainerFactoryPluginInterface, ConfigurableInterface {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $itemStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('checklist_item')
    );
  }

  /**
   * ChecklistTypeBase constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityStorageInterface $item_storage
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityStorageInterface $item_storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->itemStorage = $item_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

  /**
   * Get the storage handler for items.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   */
  public function itemStorage() {
    return $this->itemStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function getChecklist(FieldableEntityInterface $entity, string $key): ChecklistInterface {
    return new Checklist($this, $entity, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function completeChecklist(FieldableEntityInterface $entity, string $key) {}

}
