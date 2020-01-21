<?php

namespace Drupal\identity_address_data\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\IdentityMatch;
use Drupal\identity\Plugin\IdentityDataClass\IdentityDataClassBase;

/**
 * Class Address
 *
 * @IdentityDataClass(
 *   id = "address",
 *   label = @Translation("Address"),
 *   plural_label = @Translation("Addresses"),
 *   form_defaults = {
 *     "weight" = 1
 *   }
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
      ->setSetting('available_countries', ['US' => 'US'])
      ->setSetting('field_overrides', [
        'givenName' => ['override' => 'hidden'],
        'familyName' => ['override' => 'hidden'],
        'organization' => ['override' => 'hidden'],
      ])
      ->setDisplayOptions('view', [
        'type' => 'address_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
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
  public function dataLabel(IdentityData $data) {
    $render = $data->address->view([
      'type' => 'address_plain',
      'label' => 'hidden',
    ]);
    return drupal_render($render);
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    /** @var \Drupal\identity\Entity\Query\IdentityDataQueryInterface $query */
    $query = $this->identityDataStorage->getQuery();
    $query->identityDistinct();

    $query->condition('class', $this->pluginId);
    $query->exists('identity');
    $query->sort('identity', 'ASC');
    $query->range(0 , 50);

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
      if ($data->address->address_line2) {
        $query->condition('address.address_line2', $data->address->address_line2);
      }
    }

    if ($data->address->given_name && $data->address->family_name) {
      $query->condition('address.given_name', $data->address->given_name);
      $query->condition('address.family_name', $data->address->family_name);
      $has_personal_name = TRUE;
    }

    if ($data->address->organization) {
      $query->condition('address.organization', $data->address->organization);

      // We do this to stop individuals being found as organizations.
      if (!$has_personal_name) {
        $query->notExists('address.given_name');
        $query->notExists('address.family_name');
      }

      $has_org_name = TRUE;
    }

    if (!$enough_data) {
      return [];
    }
    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      if ($match_data->getIdentityId()) {
        $matches[$match_data->getIdentityId()] = new IdentityMatch(
          $data,
          $match_data,
          ($has_org_name || $has_personal_name) ? 100 : 50
        );
      }
    }
    return $matches;
  }

  /**
   * {@inheritdoc}
   */
  public function supportOrOppose(IdentityData $search_data, IdentityMatch $match) {
    $query = $this->identityDataStorage->getQuery();
    $query->identityDistinct();
    $query->condition('identity', $match->getIdentityId());
    $query->condition('address.country_code', $search_data->address->country_code);
    $query->condition('address.postal_code', $search_data->address->postal_code);

    $street_query = clone $query;
    $street_query->condition('address.address_line1', $search_data->address->address_line1);
    $street_query->condition('address.address_line2', $search_data->address->address_line2);

    if ($search_data->address->given_name || $search_data->address->family_name) {
      $personal_query = clone $street_query;
      $personal_query->condition('address.given_name', $search_data->address->given_name);
      $personal_query->condition('address.family_name', $search_data->address->family_name);

      if ($ids = $personal_query->execute()) {
        $match->supportMatch(
          $search_data,
          $this->identityDataStorage->load(reset($ids)),
          100,
          ['postal_code', 'street', 'personal_name']
        );
        return;
      }
    }
    else if ($search_data->address->organization) {
      $organization_query = clone $street_query;
      $organization_query->condition('address.organization', $search_data->address->organization);

      if ($ids = $organization_query->execute()) {
        $match->supportMatch(
          $search_data,
          $this->identityDataStorage->load(reset($ids)),
          100,
          ['postal_code', 'street', 'organization_name']
        );
        return;
      }
    }

    if ($ids = $street_query->execute()) {
      $match->supportMatch(
        $search_data,
        $this->identityDataStorage->load(reset($ids)),
        70,
        ['postal_code', 'street']
      );
    }
    else if ($ids = $query->execute()) {
      $match->supportMatch(
        $search_data,
        $this->identityDataStorage->load(reset($ids)),
        30,
        ['postal_code']
      );
    }
  }

  public function typeOptions() {
    return [
      static::TYPE_MAILING => new TranslatableMarkup('Mailing'),
      static::TYPE_PHYSICAL => new TranslatableMarkup('Physical'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function possibleMatchSupportLevels(IdentityDataInterface $search_data) {
    $levels = ['postal_code', 'street'];
    if ($search_data->address->given_name) {
      $levels[] = 'personal_name';
    }
    if ($search_data->address->organization) {
      $levels[] = 'organization_name';
    }

    return $levels;
  }
}
