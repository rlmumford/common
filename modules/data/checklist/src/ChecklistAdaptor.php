<?php

namespace Drupal\checklist;

use Drupal\Core\TypedData\TypedData;

/**
 * Typed data adaptor for checklists.
 */
class ChecklistAdaptor extends TypedData {

  /**
   * The checklist instance.
   *
   * @var \Drupal\checklist\ChecklistInterface
   */
  protected $checklist = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->checklist !== NULL) {
      return $this->checklist;
    }

    $checklist = $this->getLocalValue();
    if (!$checklist) {
      return NULL;
    }

    /** @var \Drupal\checklist\ChecklistTempstoreRepository $checklist_repo */
    $checklist_repo = \Drupal::service('checklist.tempstore_repository');
    if ($checklist_repo->has($checklist)) {
      $this->checklist = $checklist_repo->get($checklist);
    }
    return $this->checklist;
  }

  /**
   * Gets the attached checklist, constructing it locally when not yet present.
   *
   * Unlike legacy getValue(), this never fetches another tempstore snapshot.
   * An already attached checklist is preserved, including unsaved item state.
   *
   * @return \Drupal\checklist\ChecklistInterface|null
   *   The checklist on this field item, or NULL without a checklist plugin.
   */
  public function getLocalValue(): ?ChecklistInterface {
    if ($this->checklist !== NULL) {
      return $this->checklist;
    }

    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $this->getParent();

    /** @var \Drupal\checklist\Plugin\ChecklistType\ChecklistTypeInterface $checklist_type */
    $checklist_type = $item->plugin;
    if (!$checklist_type) {
      return NULL;
    }

    $key = $item->getFieldDefinition()->getName();
    if ($item->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() !== 1) {
      $key .= ':' . $item->getName();
    }

    $this->checklist = $checklist_type->getChecklist(
      $item->getEntity(),
      $key
    );

    return $this->checklist;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->checklist = $value;

    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, $arguments) {
    if (!$this->getValue()) {
      throw new \BadMethodCallException("No checklist available.");
    }

    if (is_callable([$this->getValue(), $name])) {
      return $this->getValue()->{$name}(...$arguments);
    }

    throw new \BadMethodCallException(
      "Method {$name} not found on " . get_class($this->getValue())
    );
  }

}
