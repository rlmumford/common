<?php

namespace Drupal\checklist\Plugin\ChecklistType;

use Drupal\checklist\ChecklistInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\entity\BundlePlugin\BundlePluginInterface;

/**
 * Interface ChecklistTypeInterface for checklist type plugins.
 */
interface ChecklistTypeInterface extends PluginInspectionInterface, BundlePluginInterface {

  /**
   * Get the storage handler for items.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The item storage.
   */
  public function itemStorage();

  /**
   * Get the default items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   *   The default items.
   */
  public function getDefaultItems() : array;

  /**
   * Get the checklist.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $key
   *   The key.
   *
   * @return \Drupal\checklist\ChecklistInterface
   *   The checklist object.
   */
  public function getChecklist(FieldableEntityInterface $entity, string $key) : ChecklistInterface;

  /**
   * Complete the checklist.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist to complete.
   */
  public function completeChecklist(ChecklistInterface $checklist);

}
