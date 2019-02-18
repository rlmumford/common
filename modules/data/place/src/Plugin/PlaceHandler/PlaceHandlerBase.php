<?php

namespace Drupal\place\Plugin\PlaceHandler;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\PluginBase;

class PlaceHandlerBase extends PluginBase implements PlaceHandlerInterface {

  /**
   * Get the field definitions associated with this place handler
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   */
  public function fieldDefinitions() {
    $fields = [];

    $fields['geo'] = BaseFieldDefinition::create('geofield')
      ->setLabel('Geolocation')
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }
}
