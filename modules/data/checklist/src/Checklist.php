<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Handles checklists.
 */
class Checklist implements ChecklistInterface {

  /**
   * The checklist type plugin.
   *
   * @var \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface
   */
  protected $type;

  /**
   * The checklist entity.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected $entity;

  /**
   * The checklist key.
   *
   * @var string
   */
  protected $key;

  /**
   * Items in the checklist.
   *
   * @var \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  protected $items = NULL;

  /**
   * Items removed from the checklist.
   *
   * @var \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  protected $removedItems = [];

  /**
   * TRUE if the checklist is complete, FALSE otherwise.
   *
   * @var bool
   */
  protected $isComplete;

  /**
   * Checklist constructor.
   *
   * @param \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface $type
   *   The checklist type plugin.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity the checklist is attached to.
   * @param string $key
   *   The key of the checklist.
   */
  public function __construct(
    ChecklistTypeInterface $type,
    FieldableEntityInterface $entity,
    string $key,
  ) {
    // @todo Add validation that this trio is valid.
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
   * {@inheritdoc}
   */
  public function getItems(): array {
    if ($this->items !== NULL) {
      return $this->items;
    }

    $this->items = [];

    // Load first if the entity has an id to load by.
    if ($this->getEntity()->id()) {
      $ids_to_load = $this->getType()->itemStorage()
        ->getQuery()
        // Completion must inspect every item, including during cron. Access
        // to the checklist is checked against its containing entity.
        ->accessCheck(FALSE)
        ->condition('checklist.target_id', $this->getEntity()->id())
        ->condition('checklist.checklist_key', $this->getKey())
        ->execute();
      /** @var \Drupal\checklist\Entity\ChecklistItemInterface $item */
      foreach ($this->getType()->itemStorage()->loadMultiple($ids_to_load) as $item) {
        $this->items[$item->getName()] = $item;
      }
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
   * {@inheritdoc}
   */
  public function getOrderedItems(): array {
    // @todo Implement sorting
    return $this->getItems();
  }

  /**
   * {@inheritdoc}
   */
  public function hasItem(string $name): bool {
    $this->getItems();
    return isset($this->items[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getItem(string $name): ?ChecklistItemInterface {
    $this->getItems();
    return $this->items[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(string $name) {
    $this->removedItems[$name] = $this->getItem($name);
    unset($this->items[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function setItem(string $name, ChecklistItemInterface $item) {
    if ($item->name->isEmpty()) {
      $item->name = $name;
    }

    if ($item->name->value !== $name) {
      throw new \InvalidArgumentException("Invalid checklist item supplied for {$name}.");
    }

    $this->items[$name] = $item;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function process(): ?bool {
    $items = $this->getOrderedItems();

    if (empty($items)) {
      return NULL;
    }

    $context_preparer = \Drupal::service('checklist.context_preparer');
    foreach ($items as $item) {
      if ($item->isComplete() || $item->get('status')->value === ChecklistItemInterface::STATUS_NA) {
        continue;
      }
      if (!$context_preparer->prepare($this, $item)) {
        continue;
      }
      if ($item->isApplicable() !== TRUE) {
        continue;
      }

      if ($item->getMethod() !== ChecklistItemInterface::METHOD_AUTO) {
        continue;
      }

      // @todo Bring in dependencies.
      if ($item->isActionable()) {
        try {
          $item->action();
          if (!$item->isComplete()) {
            $item->setAttempted();
          }
        }
        catch (\Exception $e) {
          $item->setFailed(ChecklistItemInterface::METHOD_AUTO);
        }
      }

      $item->save();
    }

    // Outcomes can change earlier items' gates; re-evaluate after actions.
    $resolvable = $this->isCompletable();
    if ($resolvable) {
      $this->complete();
    }
    return $resolvable;
  }

  /**
   * {@inheritdoc}
   */
  public function complete() {
    if (!$this->isCompletable()) {
      throw new \LogicException('Checklist items are not ready for completion.');
    }
    $this->getType()->completeChecklist($this);
    $this->isComplete = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isComplete() : bool {
    if (is_null($this->isComplete)) {
      $this->isComplete = $this->getType()->isChecklistComplete($this);
    }

    return $this->isComplete;
  }

  /**
   * {@inheritdoc}
   */
  public function isCompletable() : bool {
    $context_preparer = \Drupal::service('checklist.context_preparer');
    $completable = TRUE;
    foreach ($this->getItems() as $item) {
      if ($item->isComplete() || $item->get('status')->value === ChecklistItemInterface::STATUS_NA) {
        continue;
      }
      if (!$context_preparer->prepare($this, $item)) {
        return FALSE;
      }
      $applicable = $item->isApplicable();
      if ($applicable === NULL) {
        return FALSE;
      }
      if ($applicable === FALSE) {
        continue;
      }

      if ($item->isRequired()) {
        $completable = FALSE;
        break;
      }
    }

    // @todo Configurable completion dependencies.
    return $completable;
  }

}
