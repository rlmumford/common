<?php

namespace Drupal\checklist\Plugin\Field\FieldType;

use Drupal\plugin_reference\Plugin\Field\FieldType\PluginReferenceItem;

/**
 * Class ChecklistItem.
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
