<?php

namespace Drupal\relationships\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class RelationshipFieldItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * Computes the values for an item list.
   */
  protected function computeValue() {
    if (!$this->getEntity()->id()) {
      return;
    }

    $relationship_type = $this->getFieldDefinition()->getSetting('relationship_type');
    $relationship_end = $this->getFieldDefinition()->getSetting('relationship_end');

    $relationship_storage = \Drupal::entityTypeManager()->getStorage('relationship');

    $query = $relationship_storage->getQuery();
    $query->condition("{$relationship_end}.target_id", $this->getEntity()->id());
    $query->condition("type.target_id", $relationship_type);

    $delta = 0;
    foreach ($query->execute() as $relationship_id) {
      $this->list[$delta] = $this->createItem($delta, $relationship_id);
      $delta++;
    }
  }
}
