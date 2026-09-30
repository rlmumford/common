<?php

namespace Drupal\checklist\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Persists checklist lifecycle metadata and cleans up completed working state.
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
    // Direct status writes and presave hooks must obey the same lifecycle as
    // setComplete()/setIncomplete(). Leave legacy completed rows undated.
    if (!$entity->isComplete()) {
      $entity->set('completed', []);
    }
    elseif ($entity->get('completed')->isEmpty() && (!isset($entity->original) || !$entity->original->isComplete())) {
      $entity->set('completed', \Drupal::time()->getCurrentTime());
    }
    if ($names && !in_array('completed', $names, TRUE)) {
      $names[] = 'completed';
    }
    parent::doSaveFieldItems($entity, $names);
  }

}
