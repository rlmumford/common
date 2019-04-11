<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\flexilayout_builder\LayoutThirdPartySettingsInterface;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage as CoreDefaultsSectionStorage;

class DefaultsSectionStorage extends CoreDefaultsSectionStorage implements LayoutThirdPartySettingsInterface {
  use ConfigurableContextSectionStorageTrait;

  /**
   * {@inheritdoc}
   */
  protected function getRelationshipsConfiguration() {
    return $this->getThirdPartySetting('flexilayout_builder', 'relationships', []);
  }

  /**
   * {@inheritdoc}
   */
  protected function getStaticContextConfiguration() {
    return $this->getThirdPartySetting('flexilayout_builder', 'static_context', []);
  }
}
