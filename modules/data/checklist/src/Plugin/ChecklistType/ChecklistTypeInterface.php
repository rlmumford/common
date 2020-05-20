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
   */
  public function itemStorage();

  /**
   * Get the default items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function getDefaultItems();

  /**
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param string $key
   *
   * @return \Drupal\checklist\ChecklistInterface
   */
  public function getChecklist(FieldableEntityInterface $entity, string $key) : ChecklistInterface;

}
