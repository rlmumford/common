<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage as CoreDefaultsSectionStorage;

class DefaultsSectionStorage extends CoreDefaultsSectionStorage implements DisplayWideConfigSectionStorageInterface {
  use DisplayWideConfigSectionStorageTrait;

  /**
   * {@inheritdoc}
   */
  public function getConfig($key = '') {
    return $key ? $this->getThirdPartySetting('flexilayout_builder', $key) : $this->getThirdPartySettings('flexilayout_builder');
  }

  /**
   * {@inheritdoc}
   */
  public function setConfig($key, $config) {
    $this->setThirdPartySetting('flexilayout_builder', $key, $config);
    return $this;
  }
}
