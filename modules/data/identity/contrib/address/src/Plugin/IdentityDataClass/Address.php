<?php

namespace Drupal\identity_address_data\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;
use Drupal\identity\Plugin\IdentityDataClass\IdentityDataClassBase;

/**
 * Class Address
 *
 * @IdentityDataClass(
 *   id = "address",
 *   label = @Translation("Address"),
 * );
 *
 * @package Drupal\identity_address_data\Plugin\IdentityDataClass
 */
class Address extends IdentityDataClassBase {

  const TYPE_MAILING = 'mailing';
  const TYPE_PHYSICAL = 'physical';

  /**
   * {@inheritdoc}
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);

    if (is_array($value)) {
      $data->address = $value;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['address'] = BundleFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('available_countries', ['US' => 'US'])
      ->setSetting('field_overrides', [
        'givenName' => ['override' => 'hidden'],
        'familyName' => ['override' => 'hidden'],
        'organization' => ['override' => 'hidden']
      ])
      ->setDisplayOptions('form', [
        'type' => 'address',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // @todo: Consider storing geolocation data for better supporting logic.

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    $query = $this->identityDataStorage->getQuery();

    $query->condition('class', $this->pluginId);
    $has_personal_name = $has_org_name = $has_admin_local = $enough_data = FALSE;
    if ($data->address->country_code) {
      $query->condition('address.country_code', $data->address->country_code);
    }
    if ($data->address->administrative_area) {
      $has_admin_local = TRUE;
      $query->condition('address.administrative_area', $data->address->administrative_area);
    }
    if ($data->address->locality) {
      $has_admin_local = TRUE;
      $query->condition('address.locality', $data->address->locality);
    }
    if ($data->address->address_line1) {
      $enough_data = $has_admin_local;
      $query->condition('address.address_line1', $data->address->address_line1);
    }

    if ($data->address->given_name && $data->address->family_name) {
      $query->condition('address.given_name', $data->address->given_name);
      $query->condition('address.family_name', $data->address->family_name);
      $has_personal_name = TRUE;
    }

    if ($data->address->organization) {
      $query->condition('address.organization', $data->address->organization);
      $has_org_name = TRUE;
    }

    if (!$enough_data) {
      return [];
    }
    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      $matches[$match_data->getIdentity()->id()] = new IdentityMatch(
        ($has_org_name || $has_personal_name) ? 100 : 50,
        $match_data,
        $data
      );
    }
    return $matches;
  }

  /**
   * {@inheritdoc}
   */
  public function supportOrOppose(IdentityData $data, IdentityMatch $match) {
    $identity = $match->getIdentity();
    foreach ($identity->getData($this->pluginId) as $identity_data) {
      if (
        $data->address->country_code == $identity_data->address->country_code &&
        $data->address->administrative_area == $identity_data->address->administrative_area &&
        $data->address->locality == $identity_data->address->locality &&
        $data->address->address_line1 == $identity_data->address->address_line1
      ) {
        $match->supportMatch($identity_data, 10);
      }
    }
  }

  public function typeOptions() {
    return [
      static::TYPE_MAILING => new TranslatableMarkup('Mailing'),
      static::TYPE_PHYSICAL => new TranslatableMarkup('Physical'),
    ];
  }
}
