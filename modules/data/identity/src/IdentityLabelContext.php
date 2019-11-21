<?php

namespace Drupal\identity;

class IdentityLabelContext {

  /**
   * Constants for supported data preference
   */
  const DATA_PREFERENCE_CLASS = 'dpclass';
  const DATA_PREFERENCE_TYPE = 'dptype';

  /**
   * Contextual information related to the usage.
   *
   * @var array
   */
  protected $usageInfo = [];

  /**
   * Contextual information related to data preferences.
   *
   * @var array
   */
  protected $dataPreferences = [];

  /**
   * IdentityLabelContext constructor.
   *
   * @param array $data_preferences
   * @param array $usage_info
   */
  public function __construct(array $data_preferences = [], array $usage_info = []) {
    $this->usageInfo = $usage_info;
    $this->dataPreferences = $data_preferences;
  }

  /**
   * Get the usage info for this label.
   *
   * @param string $key
   *
   * @return array|mixed
   */
  public function getUsageInfo($key = NULL) {
    return $key ? $this->usageInfo[$key] : $this->usageInfo;
  }

  /**
   * Set the usage info.
   *
   * @param $key
   * @param $value
   *
   * @return $this
   */
  public function setUsageInfo($key, $value) {
    $this->usageInfo[$key] = $value;
    return $this;
  }

  /**
   * Get a data preference.
   *
   * @param $key
   * @param null $default
   *
   * @return mixed|null
   */
  public function getDataPreference($key, $default = NULL) {
    return isset($this->dataPreferences[$key]) ? $this->dataPreferences[$key] : $default;
  }

  /**
   * Set a data preference.
   *
   * @param $key
   * @param $value
   *
   * @return $this
   */
  public function setDataPreference($key, $value) {
    $this->dataPreferences[$key] = $value;
    return $this;
  }

  /**
   * @return string
   */
  public function getCacheCid() {
    $addition = '';
    if (!empty($this->dataPreferences[static::DATA_PREFERENCE_CLASS])) {
      $addition .= ":dpc:". $this->dataPreferences[static::DATA_PREFERENCE_CLASS];
    }
    if (!empty($this->dataPreferences[static::DATA_PREFERENCE_TYPE])) {
      $addition .= ":dpt:".$this->dataPreferences[static::DATA_PREFERENCE_TYPE];
    }

    return $addition;
  }

}
