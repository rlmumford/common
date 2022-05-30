<?php

namespace Drupal\checklist\PluginForm;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for plugins that need to define their own form object class.
 *
 * Plugin form classes are expected to be used by other form classes to build
 * the form, and are not stored against the form state object. This class allows
 * the plugin form class to say it needs to implement a specific class as its
 * form object. That class should extend the default provided.
 */
interface CustomFormObjectClassInterface {

  /**
   * Get the class to use for the form object.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   * @param string $default_class
   *   The default class.
   *
   * @return string
   *   The name of the class to use instead of default.
   */
  public static function getFormObjectClass(PluginInspectionInterface $plugin, string $default_class) : string;

}
