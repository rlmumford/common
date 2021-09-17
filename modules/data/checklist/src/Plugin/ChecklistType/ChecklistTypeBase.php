<?php

namespace Drupal\checklist\Plugin\ChecklistType;

use Drupal\checklist\Checklist;
use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Event\ChecklistEvent;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base plugin class for checklist types.
 */
abstract class ChecklistTypeBase extends PluginBase implements ChecklistTypeInterface, ContainerFactoryPluginInterface, ConfigurableInterface {

  /**
   * The item storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $itemStorage;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('checklist_item'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * ChecklistTypeBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $item_storage
   *   The item storage.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityStorageInterface $item_storage,
    EventDispatcherInterface $event_dispatcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->itemStorage = $item_storage;
    $this->eventDispatcher = $event_dispatcher;
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
   *   The checklist item storage.
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
  public function completeChecklist(ChecklistInterface $checklist) {
    $event = new ChecklistEvent($checklist);
    $this->eventDispatcher->dispatch('checklist.complete', $event);

    $entity_type = $checklist->getEntity()->getEntityTypeId();
    $this->eventDispatcher->dispatch(
      "checklist.complete.{$entity_type}",
      $event
    );
    $this->eventDispatcher->dispatch(
      "checklist.complete.{$entity_type}.{$checklist->getKey()}",
      $event
    );
  }

}
