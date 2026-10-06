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
   * Names supplied by the current definition catalog.
   *
   * @var array
   */
  protected array $definedItems = [];

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

    $items = [];

    // Load first if the entity has an id to load by.
    if ($this->getEntity()->id()) {
      $ids_to_load = $this->getType()->itemStorage()
        ->getQuery()
        // Completion must inspect every item, including during cron. Access
        // to the checklist is checked against its containing entity.
        ->accessCheck(FALSE)
        ->condition('checklist_type', $this->getType()->getPluginId())
        ->condition('checklist.target_id', $this->getEntity()->id())
        ->condition('checklist.checklist_key', $this->getKey())
        ->execute();
      /** @var \Drupal\checklist\Entity\ChecklistItemInterface $item */
      foreach ($this->getType()->itemStorage()->loadMultiple($ids_to_load) as $item) {
        $item->get('checklist')->entity = $this->getEntity();
        $items[$item->getName()] = $item;
      }
    }

    // Publish the cache only after all definitions have loaded successfully.
    return $this->items = $this->applyItemDefinitions($items);
  }

  /**
   * Merges current definitions into loaded or restored working items.
   */
  protected function applyItemDefinitions(array $items): array {
    $this->definedItems = [];
    // Fill in gaps, retaining persisted work and its identity.
    foreach ($this->getDefaultItems() as $name => $item) {
      $this->definedItems[$name] = TRUE;
      if (isset($items[$name])) {
        $this->applyItemDefinition($items[$name], $item);
      }
      if (!isset($items[$item->getName()])) {
        $item->checklist = [
          'entity' => $this->getEntity(),
          'checklist_key' => $this->getKey(),
        ];

        $items[$item->getName()] = $item;
      }
    }

    // Unset any removed items.
    foreach (array_keys($this->removedItems) as $name) {
      unset($items[$name]);
    }

    return $items;
  }

  /**
   * Loads definitions for this checklist's host.
   */
  protected function getDefaultItems(): array {
    return $this->getType()->getDefaultItems();
  }

  /**
   * Reconciles a stored item with its current checklist definition.
   *
   * Checklist types may extend this to resolve configuration from their source.
   * The default preserves stored configuration and updates branch membership.
   */
  protected function applyItemDefinition(ChecklistItemInterface $stored, ChecklistItemInterface $definition): void {
    if (!$definition->get('derivation')->isEmpty()) {
      $stored->set('derivation', $definition->get('derivation')->getValue());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isItemActive(ChecklistItemInterface $item): bool {
    $derivation = $item->get('derivation')->first()?->getValue() ?? [];
    if (!$derivation) {
      return TRUE;
    }
    $items = $this->getItems();
    if (!isset($this->definedItems[$item->getName()])) {
      return FALSE;
    }
    foreach ($derivation['requirements'] ?? [] as $parent => $choice) {
      $source = $items[$parent] ?? NULL;
      $outcome = is_array($choice) ? $choice['outcome'] : 'decision';
      $expected = is_array($choice) ? $choice['value'] : $choice;
      if (!$source || !$source->isComplete() || $source->get('outcomes')->get($outcome)?->getValue() !== $expected) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderedItems(): array {
    $items = $this->getItems();
    return array_replace(array_intersect_key($this->definedItems, $items), $items);
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
    return $this->items[$name] ?? NULL;
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
    $this->getItems();
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
    return \Drupal::service('checklist.processor')->process($this);
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
      if (!$this->isItemActive($item) || $item->isComplete() || $item->get('status')->value === ChecklistItemInterface::STATUS_NA) {
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
