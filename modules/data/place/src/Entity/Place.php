<?php

namespace Drupal\place\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Class Place
 *
 * @ContentEntityType(
 *   id = "place",
 *   label = @Translation("Place"),
 *   base_table = "place",
 *   revision_table = "place_revision",
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
 *     "owner" = "owner",
 *   }
 * )
 *
 * @package Drupal\place\Entity
 */
class Place extends ContentEntityBase implements  EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setDescription(new TranslatableMarkup('The name of this place'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_default',
        'label' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    /** @var \Drupal\place\PlaceHandlerPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.place.place_handler');
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    try {
      /** @var \Drupal\place\Plugin\PlaceHandler\PlaceHandlerInterface $plugin */
      $plugin = $manager->createInstance($bundle);
      $fields += $plugin->fieldDefinitions($base_field_definitions);
    }
    catch (PluginNotFoundException $e) {}

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->placeHandler()->onPreSave($this);
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($name) {
    parent::onChange($name);

    // Notify handler.
    $this->placeHandler()->onChange($this, $name);
  }

  /**
   * Get the place handler.
   *
   * @return \Drupal\place\Plugin\PlaceHandler\PlaceHandlerInterface
   */
  protected function placeHandler() {
    $manager = \Drupal::service('plugin.manager.place.place_handler');
    /** @var \Drupal\place\Plugin\PlaceHandler\PlaceHandlerInterface $handler */
    return $manager->createInstance($this->bundle());
  }

}
