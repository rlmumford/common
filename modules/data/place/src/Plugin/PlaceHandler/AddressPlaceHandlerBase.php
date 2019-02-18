<?php

namespace Drupal\place\Plugin\PlaceHandler;

class AddressPlaceHandlerBase extends PlaceHandlerBase {

  public function fieldDefinitions() {
    $fields = parent::fieldDefinitions();

    $fields['address'] =  BaseFieldDefinition::create('address')
      ->setLabel(t('Postal Address'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'address_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }
}
