<?php

namespace Drupal\exec_environment;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\StorageCacheInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Config factory service that is aware of the environment in the stack.
 *
 * @package Drupal\exec_environment
 */
class EnvironmentConfigFactory extends ConfigFactory {

  /**
   * The environment stack.
   *
   * @var \Drupal\exec_environment\EnvironmentStackInterface
   */
  protected $environmentStack;

  /**
   * The environment storages.
   *
   * @var \Drupal\Core\Config\StorageInterface[]
   */
  protected $environmentStorages = [];

  /**
   * EnvironmentConfigFactory constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The configuration storage engine.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   An event dispatcher instance to use for configuration events.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed configuration manager.
   * @param \Drupal\exec_environment\EnvironmentStackInterface $environment_stack
   *   The environment stack.
   */
  public function __construct(
    StorageInterface $storage,
    EventDispatcherInterface $event_dispatcher,
    TypedConfigManagerInterface $typed_config,
    EnvironmentStackInterface $environment_stack
  ) {
    parent::__construct($storage, $event_dispatcher, $typed_config);

    $this->environmentStack = $environment_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();

    // We are only concerned with config objects that belong to a collection
    // that we might have loaded from.
    if (!$this->configHasRelevantStorage($saved_config)) {
      return;
    }

    // Ensure that the static cache contains up to date configuration objects by
    // replacing the data on any entries for the configuration object apart
    // from the one that references the actual config object being saved.
    foreach ($this->getConfigCacheKeys($saved_config->getName()) as $cache_key) {
      $cached_config = $this->cache[$cache_key];
      if (
        $cached_config !== $saved_config &&
        $cached_config->getStorage()->getCollectionName() === $saved_config->getStorage()->getCollectionName()
      ) {
        // We can not just update the data since other things about the object
        // might have changed. For example, whether or not it is new.
        $this->cache[$cache_key]->initWithData($saved_config->getRawData());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    $deleted_config = $event->getConfig();

    if (!$this->configHasRelevantStorage($deleted_config)) {
      return;
    }

    // Ensure that the static cache does not contain deleted configuration.
    foreach ($this->getConfigCacheKeys($deleted_config->getName()) as $cache_key) {
      if ($this->cache[$cache_key]->getStorage()->getCollectionName() === $deleted_config->getStorage()->getCollectionName()) {
        unset($this->cache[$cache_key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigCacheKey($name, $immutable) {
    $suffix = $this->getEnvironmentCacheKeySuffix();
    if ($immutable) {
      $suffix .= ':' . implode(':', $this->getCacheKeys());
    }
    return $name . $suffix;
  }

  /**
   * Determine whether a config has a storage relevant to this factory.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The configuration to check.
   *
   * @return bool
   *   TRUE if the config has a relevant storage, FALSE otherwise.
   */
  protected function configHasRelevantStorage(Config $config) : bool {
    // We are only concerned with config objects that belong to a collection
    // that we might have loaded from.
    $relevant_storage = $config->getStorage()->getCollectionName() === $this->storage->getCollectionName();
    if (!$relevant_storage) {
      foreach ($this->environmentStorages as $storage) {
        if ($config->getStorage()->getCollectionName() === $storage->getCollectionName()) {
          $relevant_storage = TRUE;
          break;
        }
      }
    }
    return $relevant_storage;
  }

  /**
   * Get the part of the cache key related to the suffix.
   *
   * @return string
   *   The cache key suffix.
   */
  protected function getEnvironmentCacheKeySuffix() : string {
    $environment_keys = [];
    foreach ($this->getEnvironmentConfigComponents() as $component) {
      $environment_keys = array_merge($environment_keys, $component->getConfigCacheKeys());
    }
    return !empty($environment_keys) ? ':' . implode(':', $environment_keys) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    $result = $this->storage->listAll($prefix);
    foreach ($this->getEnvironmentConfigComponents() as $component) {
      $result = array_merge($result, $this->getEnvironmentStorage($component)->listAll($prefix));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function reset($name = NULL) {
    parent::reset($name);

    foreach ($this->environmentStorages as $storage) {
      if ($storage instanceof StorageCacheInterface) {
        $storage->resetListCache();
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($old_name, $new_name) {
    foreach ($this->getEnvironmentConfigComponents() as $component) {
      $this->getEnvironmentStorage($component)->rename($old_name, $new_name);
    }

    return parent::rename($old_name, $new_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $names, $immutable = TRUE) {
    $list = [];

    foreach ($names as $key => $name) {
      $cache_key = $this->getConfigCacheKey($name, $immutable);
      if (isset($this->cache[$cache_key])) {
        $list[$name] = $this->cache[$cache_key];
        unset($names[$key]);
      }
    }

    // Pre-load remaining configuration files.
    if (!empty($names)) {
      // Initialize override information.
      $module_overrides = [];

      $storage_data = [];
      $loading_names = array_combine($names, $names);
      foreach ($this->getEnvironmentConfigComponents() as $component) {
        if (!empty($loading_names)) {
          $env_storage = $this->getEnvironmentStorage($component);
          foreach ($env_storage->readMultiple(array_values($loading_names)) as $name => $data) {
            unset($loading_names[$name]);
            $storage_data[$name] = [$env_storage, $data];
          }
        }
      }
      if (!empty($loading_names)) {
        foreach ($this->storage->readMultiple(array_values($loading_names)) as $name => $data) {
          $storage_data[$name] = [$this->storage, $data];
        }
      }

      if ($immutable && !empty($storage_data)) {
        // Only get module overrides if we have configuration to override.
        $module_overrides = $this->loadOverrides($names);
      }

      foreach ($storage_data as $name => [$storage, $data]) {
        $cache_key = $this->getConfigCacheKey($name, $immutable);

        $this->cache[$cache_key] = $this->createConfigObject($name, $immutable, $storage);
        $this->cache[$cache_key]->initWithData($data);
        if ($immutable) {
          if (isset($module_overrides[$name])) {
            $this->cache[$cache_key]->setModuleOverride($module_overrides[$name]);
          }
          if (isset($GLOBALS['config'][$name])) {
            $this->cache[$cache_key]->setSettingsOverride($GLOBALS['config'][$name]);
          }
        }

        $this->propagateConfigOverrideCacheability($cache_key, $name);

        $list[$name] = $this->cache[$cache_key];
      }
    }

    return $list;
  }

  /**
   * Creates a configuration object.
   *
   * @param string $name
   *   Configuration object name.
   * @param bool $immutable
   *   Determines whether a mutable or immutable config object is returned.
   * @param \Drupal\Core\Config\StorageInterface|null $storage
   *   (Optional) The storage that should be set on the config object.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  protected function createConfigObject($name, $immutable, StorageInterface $storage = NULL) {
    // Note that we set the storage to the best storage to use based on the
    // current environment, rather than the storage it got loaded from. This is
    // to ensure that if the config gets saved it always saves to the best fit
    // for the current environment and that caches are then handled correctly.
    if ($immutable) {
      return new ImmutableConfig($name, $this->getBestEnvironmentStorageForConfig($name), $this->eventDispatcher, $this->typedConfigManager);
    }
    return new Config($name, $this->getBestEnvironmentStorageForConfig($name), $this->eventDispatcher, $this->typedConfigManager);
  }

  /**
   * Get the relevant environment components.
   *
   * @return \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface[]
   *   The environment components.
   */
  protected function getEnvironmentConfigComponents() : array {
    return array_filter(
      $this->environmentStack->getActiveEnvironment()->getComponents(ConfigFactoryCollectionComponentInterface::class),
      function ($component) {
        return !empty($component->getConfigCollectionName());
      }
    );
  }

  /**
   * Get the storage for a given environment component.
   *
   * @param \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface $component
   *   The environment component.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage.
   */
  protected function getEnvironmentStorage(ConfigFactoryCollectionComponentInterface $component) : StorageInterface {
    $collection = 'environment:' . $component->getConfigCollectionName();
    if (!isset($this->environmentStorages[$collection])) {
      $this->environmentStorages[$collection] = $this->storage->createCollection($collection);
    }

    return $this->environmentStorages[$collection];
  }

  /**
   * Get the best storage to use for new config objects.
   *
   * When using doGet to create a new config object that could not be loaded
   * from any of the storages, we need to select which of the environment
   * storages to assign the configuration object to. This method detects the
   * best environment storage to use.
   *
   * Currently this just takes the component with the highest priority, but we
   * may want a method on the plugin that says whether or not it's available.
   *
   * @param string $name
   *   The config name.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The best storage to use.
   */
  protected function getBestEnvironmentStorageForConfig(string $name) {
    foreach ($this->getEnvironmentConfigComponents() as $component) {
      return $this->getEnvironmentStorage($component);
    }

    return $this->storage;
  }

}
