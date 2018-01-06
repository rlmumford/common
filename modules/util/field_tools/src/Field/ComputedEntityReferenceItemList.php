<?php

namespace Drupal\field_tools\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 * Item list for computed field that gets the children.
 */
class ComputedEntityReferenceItemList extends EntityReferenceFieldItemList {

  /**
   * Store whether the value has been computed.
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    $this->ensurePopulated();
    return parent::referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->ensurePopulated();
    return parent::getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($include_computed = FALSE) {
    $this->ensurePopulated();
    return parent::getValue($include_computed);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensurePopulated();
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function first() {
    $this->ensurePopulated();
    return parent::first();
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    $this->ensurePopulated();
    return parent::count();
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $this->ensurePopulated();
    return parent::get($index);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($index) {
    $this->ensurePopulated();
    return parent::offsetExists($index);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet($index) {
    $this->ensurePopulated();
    return parent::offsetGet($index);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($index, $value) {
    $this->ensurePopulated();
    return parent::offsetSet($index, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($index) {
    $this->ensurePopulated();
    return parent::offsetUnset($index);
  }

  /**
   * Makes sure that the item list is never empty.
   *
   * For 'normal' fields that use database storage the field item list is
   * initially empty, but since this is a ocmputed field, it always has a
   * value.
   *
   * Make sure the item list is always populated, so this field is not skipped
   * for rendering in EntityViewDisplay and friends.
   */
  protected function ensurePopulated() {
    if (empty($this->isCalculated)) {
      $items = $this->computeItems();
      foreach ($items as $item) {
        $this->appendItem($item);
      }
      $this->isCalculated = TRUE;
    }
  }

  /**
   * Compute the items for this field.
   *
   * @return array[]
   *   An array of items. Each item will be passed to appendItem()
   */
  protected function computeItems() {
    $items = [];
    $callback = $this->getSetting('compute_callback');
    if (is_callable($callback)) {
      $items = $callback($this);
    }
    return $items;
  }
}
