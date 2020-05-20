<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

interface ChecklistInterface {

  /**
   * Get the checklist type
   *
   * @return \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface
   */
  public function getType() : ChecklistTypeInterface;

  /**
   * Get the entity this checklist is related to.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   */
  public function getEntity() : FieldableEntityInterface;

  /**
   * Get the key of this checklist item.
   *
   * @return string
   */
  public function getKey() : string;

  /**
   * Get the items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  public function getItems() : array;

  /**
   * Get the ordered items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  public function getOrderedItems() : array;

  /**
   * Check whether the checklist has an item with the given name.
   *
   * @param string $name
   *
   * @return bool
   */
  public function hasItem(string $name) : bool;

  /**
   * Get the item with a given name
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface|NULL
   */
  public function getItem(string $name) : ?ChecklistItemInterface;

  /**
   * @param string $name
   */
  public function removeItem(string $name);

  /**
   * Process the checklist
   *
   * @return bool
   *   Wheterh the checklist is complete or not.
   */
  public function process() : ?bool;

}
