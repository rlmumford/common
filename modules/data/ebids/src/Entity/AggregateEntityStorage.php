<?php
/**
 * Created by PhpStorm.
 * User: Mumford
 * Date: 08/01/2019
 * Time: 12:48
 */

namespace Drupal\ebids\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AggregateEntityStorage extends EntityStorageBase {

  /**
   * Instantiates a new instance of this entity handler.
   *
   * This is a factory method that returns a new instance of this object. The
   * factory should pass any needed dependencies into the constructor of this
   * object, but not the container itself. Every call to this method must return
   * a new instance of this object; that is, it may not implement a singleton.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this object should use.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return static
   *   A new instance of the entity handler.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    // TODO: Implement createInstance() method.
  }

  /**
   * Performs storage-specific loading of entities.
   *
   * Override this method to add custom functionality directly after loading.
   * This is always called, while self::postLoad() is only called when there are
   * actual results.
   *
   * @param array|null $ids
   *   (optional) An array of entity IDs, or NULL to load all entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Associative array of entities, keyed on the entity ID.
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // TODO: Implement doLoadMultiple() method.
  }

  /**
   * Determines if this entity already exists in storage.
   *
   * @param int|string $id
   *   The original entity ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   *
   * @return bool
   */
  protected function has($id, EntityInterface $entity) {
    // TODO: Implement has() method.
  }

  /**
   * Performs storage-specific entity deletion.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entity objects to delete.
   */
  protected function doDelete($entities) {
    // TODO: Implement doDelete() method.
  }

  /**
   * Performs storage-specific saving of the entity.
   *
   * @param int|string $id
   *   The original entity ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to save.
   *
   * @return bool|int
   *   If the record insert or update failed, returns FALSE. If it succeeded,
   *   returns SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  protected function doSave($id, EntityInterface $entity) {
    // TODO: Implement doSave() method.
  }

  /**
   * Gets the name of the service for the query for this entity storage.
   *
   * @return string
   *   The name of the service for the query for this entity storage.
   */
  protected function getQueryServiceName() {
    // TODO: Implement getQueryServiceName() method.
  }

  /**
   * Load a specific entity revision.
   *
   * @param int|string $revision_id
   *   The revision id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The specified entity revision or NULL if not found.
   *
   * @todo Deprecated in Drupal 8.5.0 and will be removed before Drupal 9.0.0.
   *   Use \Drupal\Core\Entity\RevisionableStorageInterface instead.
   *
   * @see https://www.drupal.org/node/2926958
   * @see https://www.drupal.org/node/2927226
   */
  public function loadRevision($revision_id) {
    // TODO: Implement loadRevision() method.
  }

  /**
   * Delete a specific entity revision.
   *
   * A revision can only be deleted if it's not the currently active one.
   *
   * @param int $revision_id
   *   The revision id.
   *
   * @todo Deprecated in Drupal 8.5.0 and will be removed before Drupal 9.0.0.
   *   Use \Drupal\Core\Entity\RevisionableStorageInterface instead.
   *
   * @see https://www.drupal.org/node/2926958
   * @see https://www.drupal.org/node/2927226
   */
  public function deleteRevision($revision_id) {
    // TODO: Implement deleteRevision() method.
  }
}
