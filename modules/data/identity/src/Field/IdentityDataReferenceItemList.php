<?php

namespace Drupal\identity\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class IdentityDataReferenceItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * Computes the values for an item list.
   */
  protected function computeValue() {
    $delta =  0;
    foreach ($this->identityDataIds() as $id) {
      $this->list[$delta] = $this->createItem($delta, $id);
    }
  }

  /**
   * Gets the entities referenced by this field, preserving field item deltas.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects keyed by field item deltas.
   */
  public function referencedEntities() {
    $this->ensureComputedValue();

    return parent::referencedEntities();
  }

  protected function identityDataIds() {
    $field_definition = $this->getFieldDefinition();
    $data_storage = \Drupal::entityTypeManager()->getStorage('identity_data');

    $query = $data_storage->getQuery();
    $query->condition('identity', $this->getEntity()->id());

    if ($data_class = $field_definition->getSetting('identity_data_class')) {
      $query->condition('class', $data_class);
    }

    return $query->execute();
  }
}
