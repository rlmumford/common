<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class IdentityDataStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'identity.query.sql';
  }

  /**
   * {@inheritdoc}
   */
  public function doPreSave(EntityInterface $entity) {
    /** @var \Drupal\identity\Entity\IdentityData $entity */
    $return = parent::doPreSave($entity);

    $plugin = $entity->getClass();
    if (is_callable([$plugin, 'preSaveData'])) {
      $plugin->preSaveData($entity);
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function doPostSave(EntityInterface $entity, $update) {
    /** @var \Drupal\identity\Entity\IdentityData $entity */
    $plugin = $entity->getClass();
    if (is_callable([$plugin, 'postSaveData'])) {
      $plugin->postSaveData($entity);
    }

    parent::doPostSave($entity, $update);
  }

  /**
   * {@inheritdoc}
   */
  protected function invokeStorageLoadHook(array &$entities) {
    /** @var \Drupal\identity\Entity\IdentityData $entity */
    foreach ($entities as $entity) {
      $plugin = $entity->getClass();
      if (is_callable([$plugin, 'storageLoadData'])) {
        $plugin->storageLoadData($entity);
      }
    }

    parent::invokeStorageLoadHook($entities);
  }
}
