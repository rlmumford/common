<?php

namespace Drupal\place\Plugin\PlaceHandler;
use Drupal\place\Entity\Place;

/**
 * Class SimpleAddressPlaceHandler
 *
 * @PlaceHandler(
 *   id = "address",
 *   label = @Translation("Simple Address"),
 * )
 */
class SimpleAddressPlaceHandler extends AddressPlaceHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function fieldDefinitions(array $base_field_definitions) {
    $fields = parent::fieldDefinitions($base_field_definitions);

    $fields['name'] = $base_field_definitions['name']
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function onPreSave(Place $place) {
    parent::onPreSave($place);

    $place->set('name', $place->address->address_line1);
  }

  /**
   * {@inheritdoc}
   */
  public function onChange(Place $place, $name) {
    parent::onChange($place, $name);

    if ($name == 'address') {
      $place->set('name', $place->address->address_line1);
    }
  }
}
