<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
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
    $configuration = $item->configuration ? $item->configuration : [];

    try {
      $plugin_type = $item->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getSetting('plugin_type');

      /** @var \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager */
      $plugin_manager = \Drupal::service('plugin.manager.' . $plugin_type);
      $this->plugin_instance = $plugin_manager->createInstance($id, $configuration);

      // @todo: Find a way to put this that doesn't make his field type
      // depend on checklist items.
      if (
        $this->plugin_instance instanceof ChecklistItemHandlerInterface &&
        $item->getEntity() instanceof ChecklistItemInterface
      ) {
        $this->plugin_instance->setItem($item->getEntity());
      }
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
