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
      ->setDisplayConfigurable('view', TRUE)
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
  public function supportOrOppose(IdentityData $data, IdentityMatch $match) {
    $identity = $match->getIdentity();
    foreach ($identity->getData($this->pluginId) as $identity_data) {
      if (
        $data->type->value == static::TYPE_DOB &&
        $data->type->value == $identity_data->type->value &&
        $data->date->value == $identity_data->date->value
      ) {
        $match->supportMatch($identity_data, 10);
      }
    }
  }
}
