<?php

namespace Drupal\place\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\commerce_tax\Kernel\TaxRateTest;

/**
 * Class Place
 *
 * @ContentEntityType(
 *   id = "place",
 *   label = @Translation("Place"),
 *   base_table = "place",
 *   revision_table = "place_revision",
 *   data_table = "place_data",
 *   revision_data_table = "place_revision_data",
 *   handlers = {
 *     "storage" => "Drupal\place\PlaceStorage",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   }
 * )
 *
 * @package Drupal\place\Entity
 */
class Place extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setDescription(new TranslatableMarkup('The name of this place'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['address'] =  BaseFieldDefinition::create('address')
      ->setLabel(t('Postal Address'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'address_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['geo'] = BaseFieldDefinition::create('geofield')
      ->setLabel('Geolocation')
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    return $fields;
  }

}
