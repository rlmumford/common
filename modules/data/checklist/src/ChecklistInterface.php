<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Interface for checklists.
 */
interface ChecklistInterface {

  /**
   * Get the checklist type.
   *
   * @return \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface
   *   The checklist type plugin.
   */
  public function getType() : ChecklistTypeInterface;

  /**
   * Get the entity this checklist is related to.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity the checklist is connected to.
   */
  public function getEntity() : FieldableEntityInterface;

  /**
   * Get the key of this checklist item.
   *
   * @return string
   *   The checklist key.
   */
  public function getKey() : string;

  /**
   * Get the items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   *   The checklist items.
   */
  public function getItems() : array;

  /**
   * Get the ordered items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   *   The checklist items in order.
   */
  public function getOrderedItems() : array;

  /**
   * Check whether the checklist has an item with the given name.
   *
   * @param string $name
   *   The name of the checklist items.
   *
   * @return bool
   *   TRUE if the item exists, FALSE otherwise.
   */
  public function hasItem(string $name) : bool;

  /**
   * Get the item with a given name.
   *
   * @param string $name
   *   The checklist item name.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface|null
   *   The checklist item if it exists.
   */
  public function getItem(string $name) : ?ChecklistItemInterface;

  /**
   * Remove an item from the checklist.
   *
   * @param string $name
   *   The checklist item name.
   */
  public function removeItem(string $name);

  /**
   * Process the checklist.
   *
   * @return bool
   *   Whether the checklist is complete or not.
   */
  public function process() : ?bool;

  /**
   * Complete the checklist.
   */
  public function complete();

  /**
   * Is the checklist complete?
   *
   * @return bool
   *   TRUE if the checklist is complete, FALSE otherwise.
   */
  public function isComplete() : bool;

  /**
   * Can the checklist be completed?
   *
   * @return bool
   *   TRUE if the checklist can be completed, FALSE otherwise.
   */
  public function isCompletable() : bool;

}
