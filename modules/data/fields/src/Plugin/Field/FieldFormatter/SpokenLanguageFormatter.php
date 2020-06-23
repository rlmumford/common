<?php

namespace Drupal\rlm_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\LanguageFormatter;
use Drupal\rlm_fields\Plugin\Field\FieldType\SpokenLanguageItem;

/**
 * Plugin implementation of the 'language' formatter.
 *
 * @FieldFormatter(
 *   id = "spoken_language",
 *   label = @Translation("Spoken Language"),
 *   field_types = {
 *     "spoken_language"
 *   }
 * )
 */
class SpokenLanguageFormatter extends LanguageFormatter {

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    $element = parent::viewValue($item);

    $prof_map = [
      SpokenLanguageItem::PROF_MOTHER => $this->t('Mother Tongue'),
      SpokenLanguageItem::PROF_FLUENT => $this->t('Fluent'),
      SpokenLanguageItem::PROF_PART => $this->t('Partial'),
    ];
    if ($prof = $item->proficiency) {
      $element['#plain_text'] .= ' ('.$prof_map[$prof].')';
    }

    return $element;
  }

}
