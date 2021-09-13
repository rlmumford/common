<?php

namespace Drupal\exec_environment\Event;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Event for detecting render environment for entities.
 */
class EntityBuildEnvironmentDetectionEvent extends EnvironmentDetectionEvent {

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities;

  /**
   * EntityBuildEnvironmentDetectionEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entities being build.
   */
  public function __construct(EntityTypeInterface $entity_type, array $entities) {
    $this->entityType = $entity_type;
    $this->entities = $entities;
  }

  /**
   * Get the entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * Get the entities being built.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities.
   */
  public function getEntities() {
    return $this->entities;
  }

}
