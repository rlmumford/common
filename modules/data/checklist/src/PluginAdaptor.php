<?php

namespace Drupal\checklist;

use Drupal\Core\TypedData\TypedData;

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
    $configuration = $item->configuration->getValue();

    try {
      $plugin_type = $item->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getSetting('plugin_type');

      /** @var \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager */
      $plugin_manager = \Drupal::service('plugin.manager.' . $plugin_type);
      $this->plugin_instance = $plugin_manager->createInstance($id, $configuration);
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $this->plugin_instance;
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
