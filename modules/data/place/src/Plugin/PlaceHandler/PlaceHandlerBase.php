<?php

namespace Drupal\place\Plugin\PlaceHandler;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\PluginBase;
use Drupal\entity\BundleFieldDefinition;
use Drupal\place\Entity\Place;

class PlaceHandlerBase extends PluginBase implements PlaceHandlerInterface {

  /**
   * Get the field definitions associated with this place handler
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   */
  public function fieldDefinitions(array $base_field_definitions) {
    $fields = [];

    $fields['geo'] = BundleFieldDefinition::create('geofield')
      ->setLabel('Geolocation')
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setProvider('place')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * Act just before a place is saved.
   *
   * @param \Drupal\place\Entity\Place $place
   */
  public function onPreSave(Place $place) {
  }

  /**
   * {@inheritdoc}
   */
  public function onChange(Place $place, $name) {
  }

}
