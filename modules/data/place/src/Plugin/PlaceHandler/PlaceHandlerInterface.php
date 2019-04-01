<?php

namespace Drupal\place\Plugin\PlaceHandler;

use Drupal\place\Entity\Place;

interface PlaceHandlerInterface {

  /**
   * Get the field definitions associated with this place handler
   *
   * @param \Drupal\Core\Field\BaseFieldDefinition[] $base_field_definitions
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   */
  public function fieldDefinitions(array $base_field_definitions);

  /**
   * Act just before a place is saved.
   *
   * @param \Drupal\place\Entity\Place $place
   */
  public function onPreSave(Place $place);

  /**
   * Act whenever anything changes.
   *
   * @param \Drupal\place\Entity\Place $place
   * @param string $name
   *   The property that has changed
   */
  public function onChange(Place $place, $name);

}
