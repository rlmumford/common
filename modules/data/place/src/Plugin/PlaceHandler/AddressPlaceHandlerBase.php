<?php

namespace Drupal\place\Plugin\PlaceHandler;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Locale\CountryManager;
use Drupal\entity\BundleFieldDefinition;
use Drupal\place\Entity\Place;

class AddressPlaceHandlerBase extends PlaceHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function fieldDefinitions(array $base_field_definitions) {
    $fields = parent::fieldDefinitions($base_field_definitions);

    $fields['address'] =  BundleFieldDefinition::create('address')
      ->setLabel(t('Postal Address'))
      ->setRevisionable(TRUE)
      ->setProvider('place')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'address_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function onPreSave(Place $place) {
    /** @var \Drupal\geocoder_field\PreprocessorPluginManager $preprocessor_manager */
    $preprocessor_manager = \Drupal::service('plugin.manager.geocoder.preprocessor');
    /** @var \Drupal\geocoder\DumperPluginManager $dumper_manager */
    $dumper_manager = \Drupal::service('plugin.manager.geocoder.dumper');

    $address = $place->address;
    if ($place->original) {
      $original_address = $place->original->address;
    }

    // Skip any action if:
    // geofield has value and remote field value has not changed.
    if (isset($original_address) && !$place->get('geo')->isEmpty() && $address->getValue() == $original_address->getValue()) {
      return;
    }

    // If a value has been set on the initial save.
    if (!$place->get('geo')->isEmpty() && $place->isNew()) {
      return;
    }

    // First we need to Pre-process field.
    // Note: in case of Address module integration this creates the
    // value as formatted address.
    $preprocessor_manager->preprocess($address);

    $dumper = $dumper_manager->createInstance('geojson');
    $result = [];

    foreach ($address->getValue() as $delta => $value) {
      if ($address->getFieldDefinition()->getType() == 'address_country') {
        $value['value'] = CountryManager::getStandardList()[$value['value']];
      }

      $address_collection = isset($value['value']) ? \Drupal::service('geocoder')->geocode($value['value'], ['googlemaps', 'googlemaps_business']) : NULL;
      if ($address_collection) {
        $result[$delta] = $dumper->dump($address_collection->first());

        // We can't use DumperPluginManager::fixDumperFieldIncompatibility
        // because we do not have a FieldConfigInterface.
        // Fix not UTF-8 encoded result strings.
        // https://stackoverflow.com/questions/6723562/how-to-detect-malformed-utf-8-string-in-php
        if (is_string($result[$delta])) {
          if (!preg_match('//u', $result[$delta])) {
            $result[$delta] = utf8_encode($result[$delta]);
          }
        }
      }
    }

    $place->set('geo', $result);
  }
}
