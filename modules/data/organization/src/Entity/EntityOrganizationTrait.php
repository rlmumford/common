<?php

namespace Drupal\organization\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Trait EntityOrganizationTrait
 *
 * @see \Drupal\organization\Entity\EntityOrganizationInterface
 *
 * @package Drupal\organization\Entity
 */
trait EntityOrganizationTrait {

  /**
   * {@inheritdoc}
   */
  public function getOrganization() {
    $key = $this->getEntityType()->getKey('organization');
    return $this->{$key}->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrganization(Organization $organization) {
    $key = $this->getEntityType()->getKey('organization');
    $this->{$key} = $organization;
    return $this;
  }

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   */
  public static function organizationBaseFieldDefinitions(EntityTypeInterface $entity_type) {
    if (!is_subclass_of($entity_type->getClass(), EntityOrganizationInterface::class)) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not implement \Drupal\organization\Entity\EntityOrganizationInterface.');
    }
    if (!$entity_type->hasKey('organization')) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not have an "organization" entity key.');
    }

    return [
      $entity_type->getKey('organization') => BaseFieldDefinition::create('entity_reference')
        ->setLabel(new TranslatableMarkup('User ID'))
        ->setSetting('target_type', 'organization')
        ->setTranslatable($entity_type->isTranslatable())
        ->setDefaultValueCallback(static::class . '::getDefaultEntityOrganization'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultEntityOrganization(EntityInterface $entity, FieldDefinitionInterface $field_definition) {
    return NULL;
  }
}
