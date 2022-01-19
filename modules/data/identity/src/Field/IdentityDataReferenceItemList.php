<?php

namespace Drupal\identity\Field;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Field item list class that helps relate to identity datas of a certain type.
 *
 * @package Drupal\identity\Field
 */
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

  /**
   * Get the identity data ids.
   *
   * @return int[]
   *   Ids of the identity data of this class.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function identityDataIds() {
    $query = $this->buildIdentityDataQuery();
    return $query ? $query->execute() : [];
  }

  /**
   * Get the identity id to find related datas for.
   *
   * @return int|null
   *   The identity id.
   */
  protected function getIdentityId() : ?int {
    return $this->getEntity()->id();
  }

  /**
   * Build the entity query for getting the identity datas.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface|null
   *   The entity query.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildIdentityDataQuery() : ?QueryInterface {
    $id = $this->getIdentityId();
    if (!$id) {
      return NULL;
    }

    $field_definition = $this->getFieldDefinition();
    $data_storage = \Drupal::entityTypeManager()->getStorage('identity_data');

    $query = $data_storage->getQuery();
    $query->condition('identity', $id);
    if (
      ($handler_settings = $field_definition->getSetting('handler_settings'))
      && !empty($handler_settings['target_bundles'])
    ) {
      $query->condition('class', array_keys($handler_settings['target_bundles']), 'IN');
    }

    return $query;
  }

}
