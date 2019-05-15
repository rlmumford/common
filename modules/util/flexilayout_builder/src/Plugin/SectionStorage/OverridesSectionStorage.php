<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage as CoreOverridesSectionStorage;

class OverridesSectionStorage extends CoreOverridesSectionStorage implements ThirdPartySettingsInterface {

  /**
   * The field name that stores the display settings.
   */
  const SETTINGS_FIELD_NAME = 'layout_builder__settings';

  /**
   * Sets the value of a third-party setting.
   *
   * @param string $module
   *   The module providing the third-party setting.
   * @param string $key
   *   The setting name.
   * @param mixed $value
   *   The setting value.
   *
   * @return $this
   */
  public function setThirdPartySetting($module, $key, $value) {
    $settings = $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings;
    $settings[$module][$key] = $value;
    $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings = $settings;

    return $settings;
  }

  /**
   * Gets the value of a third-party setting.
   *
   * @param string $module
   *   The module providing the third-party setting.
   * @param string $key
   *   The setting name.
   * @param mixed $default
   *   The default value
   *
   * @return mixed
   *   The value.
   */
  public function getThirdPartySetting($module, $key, $default = NULL) {
    $settings = $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings;
    if (isset($settings[$module][$key])) {
      return $settings[$module][$key];
    }

    return $default;
  }

  /**
   * Gets all third-party settings of a given module.
   *
   * @param string $module
   *   The module providing the third-party settings.
   *
   * @return array
   *   An array of key-value pairs.
   */
  public function getThirdPartySettings($module) {
    $settings = $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings;
    if (isset($settings[$module])) {
      return $settings[$module];
    }

    return [];
  }

  /**
   * Unsets a third-party setting.
   *
   * @param string $module
   *   The module providing the third-party setting.
   * @param string $key
   *   The setting name.
   *
   * @return mixed
   *   The value.
   */
  public function unsetThirdPartySetting($module, $key) {
    $settings = $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings;
    $value = $settings[$module][$key];
    unset($settings[$module][$key]);
    $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings = $settings;

    return $value;
  }

  /**
   * Gets the list of third parties that store information.
   *
   * @return array
   *   The list of third parties.
   */
  public function getThirdPartyProviders() {
    $settings = $this->getEntity()->get(static::SETTINGS_FIELD_NAME)->settings;
    return array_keys($settings);
  }
}
