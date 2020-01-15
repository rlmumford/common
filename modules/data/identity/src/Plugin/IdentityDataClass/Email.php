<?php

namespace Drupal\identity\Plugin\IdentityDataClass;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

/**
 * Class Email
 *
 * @IdentityDataClass(
 *   id = "email",
 *   label = @Translation("Email Address"),
 *   plural_label = @Translation("Email Addresses")
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class Email extends IdentityDataClassBase {

  /**
   * Type constants
   */
  const TYPE_UNKNOWN = 'unknown';
  const TYPE_PERSONAL = 'personal';
  const TYPE_WORK = 'work';
  const TYPE_OTHER = 'other';

  /**
   * {@inheritdoc}
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);
    $data->email_address = $value;
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['email_address'] = BundleFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setDisplayOptions('view', [
        'type' => 'email_mailto',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'email_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      static::TYPE_UNKNOWN => new TranslatableMarkup('Unknown'),
      static::TYPE_PERSONAL => new TranslatableMarkup('Personal'),
      static::TYPE_WORK => new TranslatableMarkup('Work'),
      static::TYPE_OTHER => new TranslatableMarkup('Other')
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    /** @var \Drupal\identity\Entity\Query\IdentityDataQueryInterface $query */
    $query = $this->identityDataStorage->getQuery();
    $query->identityDistinct();
    $query->condition('class', $this->pluginId);
    if ($data->email_address->value) {
      $query->condition('email_address', $data->email_address->value);
    }
    else {
      return [];
    }
    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      if ($match_data->getIdentityId() && empty($matches[$match_data->getIdentityId()])) {
        /** @var IdentityData $match_data */
        $matches[$match_data->getIdentityId()] = new IdentityMatch($data, $match_data, 1000);
      }
    }
    return $matches;
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
      if ($search_data->email_address->value == $match_data->email_address->value) {
        if ($match->supportMatch($search_data, $match_data, 90)) {
          return;
        }
      }
    }
  }
}
