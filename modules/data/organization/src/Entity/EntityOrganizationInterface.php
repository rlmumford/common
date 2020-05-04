<?php

namespace Drupal\organization\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

interface EntityOrganizationInterface {

  /**
   * Get the organization this entity belongs to.
   *
   * @return \Drupal\organization\Entity\Organization
   */
  public function getOrganization();

  /**
   * Set the organization.
   *
   * @param \Drupal\organization\Entity\Organization $organization
   *
   * @return static
   */
  public function setOrganization(EntityInterface $organization);

  /**
   * Get the field storage definition for organization references.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   Field definitions keyed by name.
   */
  public static function organizationBaseFieldDefinitions(EntityTypeInterface $entity_type);

}
