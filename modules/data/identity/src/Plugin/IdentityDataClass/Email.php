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
 *   label = @Translation("Email"),
 * );
 *
 * @package Drupal\identity\Plugin\IdentityDataClass
 */
class Email extends IdentityDataClassBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['email_address'] = BundleFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function typeOptions() {
    return [
      'unknown' => new TranslatableMarkup('Unknown'),
      'personal' => new TranslatableMarkup('Personal'),
      'work' => new TranslatableMarkup('Work'),
      'other' => new TranslatableMarkup('Other')
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    $query = $this->identityDataStorage->getQuery();
    $query->condition('class', $this->pluginId);
    if ($data->email_address->value) {
      $query->condition('email_address', $data->email_address->value);
    }
    else {
      return [];
    }
    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      $matches[$match_data->getIdentity()->id()] = new IdentityMatch(1000, $match_data, $data);
    }
    return $matches;
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
      if ($data->email_address->value == $identity_data->email_address->value) {
        $match->supportMatch($identity_data, 1000);
      }
    }
  }
}
