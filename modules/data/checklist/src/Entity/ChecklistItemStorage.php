<?php

namespace Drupal\checklist\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Persists checklist items and clears successful work's intermediate state.
 */
class ChecklistItemStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    // Enforce cleanup after presave hooks, including direct status writes.
    if ($entity->isComplete()) {
      $entity->clearWorkingState();
      if ($names && !in_array('state', $names, TRUE)) {
        $names[] = 'state';
      }
    }
    parent::doSaveFieldItems($entity, $names);
  }

}
