<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

class Checklist implements ChecklistInterface {

  /**
   * @var \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface
   */
  protected $type;

  /**
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected $entity;

  /**
   * @var string
   */
  protected $key;

  /**
   * @var \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  protected $items = NULL;

  /**
   * @var \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  protected $removedItems = [];

  /**
   * Checklist constructor.
   *
   * @param \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface $type
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param string $key
   */
  public function __construct(
    ChecklistTypeInterface $type,
    FieldableEntityInterface $entity,
    string $key
  ) {
    // @todo: Add validation that this trio is valid.
    $this->type = $type;
    $this->entity = $entity;
    $this->key = $key;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): ChecklistTypeInterface {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): FieldableEntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): string {
    return $this->key;
  }

  /**
   * Get the items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  public function getItems(): array {
    if ($this->items !== NULL) {
      return $this->items;
    }

    $this->items = [];

    // Load first.
    $ids_to_load = $this->getType()->itemStorage()
      ->getQuery()
      ->condition('checklist.target_id', $this->getEntity()->id())
      ->condition('checklist.checklist_key', $this->getKey())
      ->execute();
    /** @var \Drupal\checklist\Entity\ChecklistItemInterface $item */
    foreach ($this->getType()->itemStorage()->loadMultiple($ids_to_load) as $item) {
      $this->items[$item->getName()] = $item;
    }

    // Fill in gaps.
    foreach ($this->getType()->getDefaultItems() as $name => $item) {
      if (!isset($this->items[$item->getName()])) {
        $item->checklist = [
          'target_id' => $this->getEntity()->id(),
          'checklist_key' => $this->getKey(),
        ];

        $this->items[$item->getName()] = $item;
      }
    }

    // Unset any removed items.
    foreach (array_keys($this->removedItems) as $name) {
      unset($this->items[$name]);
    }

    return $this->items;
  }

  /**
   * Get the ordered items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  public function getOrderedItems(): array {
    // @todo: Implement sorting
    return $this->getItems();
  }

  /**
   * Check whether the checklist has an item with the given name.
   *
   * @param string $name
   *
   * @return bool
   */
  public function hasItem(string $name): bool {
    $this->getItems();
    return isset($this->items[$name]);
  }

  /**
   * Get the item with a given name
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function getItem(string $name): ?ChecklistItemInterface {
    $this->getItems();
    return $this->items[$name];
  }

  /**
   * @param string $name
   */
  public function removeItem(string $name) {
    $this->removedItems[$name] = $this->getItem($name);
    unset($this->items[$name]);
  }

  /**
   * Process the checklist
   *
   * @return bool
   *   Wheterh the checklist is complete or not.
   */
  public function process(): ?bool {
    $items = $this->getOrderedItems();

    if (empty($items)) {
      return NULL;
    }

    $default_resolvable = TRUE;
    foreach ($items as $item) {
      if (!$item->isApplicable() || !$item->isIncomplete()) {
        continue;
      }

      if ($item->getMethod() !== ChecklistItemInterface::METHOD_AUTO) {
        $default_resolvable = FALSE;
        continue;
      }

      // @todo: Bring in dependencies.
      if ($item->isActionable()) {
        try {
          $item->action();

          if (!$item->isComplete()) {
            $item->setAttempted();

            if ($item->isRequired()) {
              $default_resolvable = FALSE;
            }
          }
        }
        catch (\Exception $e) {
          if ($item->isRequired()) {
            $default_resolvable = FALSE;
          }

          $item->setFailed(ChecklistItemInterface::METHOD_AUTO);
        }
      }
      else {
        if ($item->isRequired()) {
          $default_resolvable = FALSE;
        }
      }

      $item->save();
    }

    // @todo: Configurable completion dependencies.
    $resolvable = $default_resolvable;

    return $resolvable;
  }
}
