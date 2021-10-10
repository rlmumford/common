<?php

namespace Drupal\plugin_reference;

use Drupal\Core\TypedData\TypedData;

/**
 * Plugin adaptor to turn id and configuration into a plugin instance.
 *
 * @package Drupal\plugin_reference
 */
class PluginAdaptor extends TypedData {

  /**
   * The plugin instance
   *
   * @var \Drupal\Component\Plugin\PluginInspectionInterface
   */
  protected $plugin_instance = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->plugin_instance !== NULL) {
      return $this->plugin_instance;
    }

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->getParent();
    $id = $item->id;
    if (!$id) {
      return NULL;
    }
    $configuration = $item->configuration ? $item->configuration : [];

    try {
      $definition = $item->getFieldDefinition()->getFieldStorageDefinition();
      if ($callback = $definition->getSetting('plugin_creation_callback')) {
        $this->plugin_instance = call_user_func($callback, $id, $configuration, $item);
      }
      else {
        $this->plugin_instance = $this->createPluginInstance($id, $configuration);
      }
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $this->plugin_instance;
  }

  /**
   * Create the plugin instance.
   *
   * @param string $id
   *   The plugin id.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return object
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createPluginInstance(string $id, array $configuration) {
    $plugin_type = $this->getParent()
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('plugin_type');

    /** @var \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.' . $plugin_type);
    return $plugin_manager->createInstance($id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->plugin_instance = $value;

    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
