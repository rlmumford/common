<?php

namespace Drupal\office\Plugin\PlaceHandler;

use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\place\Plugin\PlaceHandler\AddressPlaceHandlerBase;

/**
 * Class OfficePlaceHandler
 *
 * @PlaceHandler(
 *   id = "office",
 *   label = @Translation("Office"),
 * )
 */
class OfficePlaceHandler extends AddressPlaceHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function fieldDefinitions(array $base_field_definitions) {
    $fields = parent::fieldDefinitions($base_field_definitions);

    if (empty($fields['address'])) {
      return $fields;
    }

    $fields['address']->setSetting('field_overrides', [
      'given_name' => FieldOverride::HIDDEN,
      'family_name' => FieldOverride::HIDDEN,
    ]);

    return $fields;
  }
}
