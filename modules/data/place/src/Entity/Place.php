<?php

namespace Drupal\place\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
 *     "storage" = "Drupal\place\PlaceStorage",
 *     "access" = "Drupal\place\PlaceAccessControlHandler",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *     "bundle" = "type",
 *   }
 * )
 *
 * @package Drupal\place\Entity
 */
class Place extends ContentEntityBase {

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setDescription(new TranslatableMarkup('The name of this place'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   *
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    /** @var \Drupal\place\PlaceHandlerPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.place.place_handler');
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    try {
      /** @var \Drupal\place\Plugin\PlaceHandler\PlaceHandlerInterface $plugin */
      $plugin = $manager->createInstance($bundle);
      $fields += $plugin->fieldDefinitions();
    }
    catch (PluginNotFoundException $e) {}

    return $fields;
  }


}
