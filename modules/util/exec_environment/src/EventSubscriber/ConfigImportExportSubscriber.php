<?php

namespace Drupal\exec_environment\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to config import/export events.
 *
 * @package Drupal\exec_environment\EventSubscriber
 */
class ConfigImportExportSubscriber implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * ConfigImportExportSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $config_storage) {
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT] = 'onStorageTransformExport';
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT] = 'onStorageTransformImport';
    return $events;
  }

  /**
   * Transform the storage on export.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The storage transform event.
   */
  public function onStorageTransformExport(StorageTransformEvent $event) {
    $storage = $event->getStorage();

    $exportable_environments = $this->configFactory
      ->get('exec_environment.exported_config_environments')
      ->get('collections') ?? [];
    foreach ($storage->getAllCollectionNames() as $collection_name) {
      [$prefix, $env] = explode(':', $collection_name, 2);
      if ($prefix === 'environment' && !in_array($env, $exportable_environments)) {
        $storage->createCollection($collection_name)->deleteAll();
      }
    }
  }

  /**
   * Transform the storage on import.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The storage transform event.
   */
  public function onStorageTransformImport(StorageTransformEvent $event) {
    $storage = $event->getStorage();

    $exportable_environments = $this->configFactory
      ->get('exec_environment.exported_config_environments')
      ->get('collections') ?? [];
    foreach ($this->configStorage->getAllCollectionNames() as $collection_name) {
      [$prefix, $env] = explode(':', $collection_name, 2);
      if ($prefix === 'environment' && !in_array($env, $exportable_environments)) {
        // Make sure the storage contains all the current config in the given
        // collection.
        $target_collection = $storage->createCollection($collection_name);
        $source_collection = $this->configStorage->createCollection($collection_name);

        $target_collection->deleteAll();
        foreach ($source_collection->listAll() as $name) {
          $target_collection->write($name, $source_collection->read($name));
        }
      }
    }
  }

}
