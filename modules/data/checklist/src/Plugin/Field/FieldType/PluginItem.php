<?php

namespace Drupal\checklist\Plugin\Field\FieldType;

use Drupal\checklist\ChecklistAdaptor;
use Drupal\checklist\ChecklistPluginAdaptor;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\plugin_reference\Plugin\Field\FieldType\PluginReferenceItem;

/**
 * Class ChecklistItem
 *
 * @FieldType(
 *   id = "plugin",
 *   label = @Translation("Plugin & Configuration"),
 *   category = @Translation("Checklist"),
 *   no_ui = TRUE,
 * );
 *
 * @package Drupal\checklist\Plugin\Field\FieldType
 */
class PluginItem extends PluginReferenceItem {
}
