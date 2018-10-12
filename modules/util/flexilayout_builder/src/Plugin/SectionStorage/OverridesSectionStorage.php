<?php

namespace Drupal\flexilayout_builder\Plugin\SectionStorage;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage as CoreOverridesSectionStorage;

class OverridesSectionStorage extends CoreOverridesSectionStorage implements DisplayWideConfigSectionStorageInterface {
  use DisplayWideConfigSectionStorageTrait;
}
