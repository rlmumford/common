<?php

namespace Drupal\place\Plugin\PlaceHandler;

interface PlaceHandlerInterface {

  /**
   * Get the field definitions associated with this place handler
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   */
  public function fieldDefinitions();

}
