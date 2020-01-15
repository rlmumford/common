<?php

namespace Drupal\identity\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

/**
 * Class Date
 *
 * @IdentityDataClass(
 *   id = "date",
 *   label = @Translation("Date"),
 *   plural_label = @Translation("Dates"),
 *   form_defaults = {
 *     "weight" = 5,
 *   }
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class Date extends IdentityDataClassBase {

  /**
   * Type constants
   */
  const TYPE_DOB = 'dob';
  const TYPE_DEATH = 'death';
  const TYPE_INCORPORATION = 'incorporation';
  const TYPE_OTHER = 'other';

  /**
   * {@inheritdoc}
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);
    $data->date = $value;
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['date'] = BundleFieldDefinition::create('datetime')
      ->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATE)
      ->setLabel(new TranslatableMarkup('Date'))
      ->setDisplayOptions('view', [
        'type' => 'datetime_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_DOB => new TranslatableMarkup('Date of Birth'),
      static::TYPE_DEATH => new TranslatableMarkup('Date of Death'),
      static::TYPE_INCORPORATION => new TranslatableMarkup('Incorporation'),
      static::TYPE_OTHER => new TranslatableMarkup('Other')
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    // I don't think we want to find matches on dates?
     return [];
  }

  /**
   * Work out whether the data supports or opposes
   *
   * @param \Drupal\identity\Entity\IdentityData $data
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityData $search_data, IdentityMatch $match) {
    $identity = $match->getIdentity();
    foreach ($identity->getData($this->pluginId) as $match_data) {
      if (
        $search_data->type->value == static::TYPE_DOB &&
        $search_data->type->value == $match_data->type->value &&
        $search_data->date->value == $match_data->date->value
      ) {
        if ($match->supportMatch($search_data, $match_data, 10)) {
          return;
        }
      }
    }
  }
}
