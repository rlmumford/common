<?php

namespace Drupal\identity;

use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Entity\IdentityDataStorage;

class IdentityDataIterator implements \Iterator, \ArrayAccess {

  /**
   * Identity data ids.
   *
   * @var int[]
   */
  protected $ids;

  /**
   * Array of identity datas.
   *
   * @var \Drupal\identity\Entity\IdentityDataInterface[]
   */
  protected $entities = [];

  /**
   * The current pointer.
   *
   * @var int
   */
  protected $current = 0;

  /**
   * The batch size.
   *
   * @var int
   */
  protected $batchSize = 1;

  /**
   * The identity data storage.
   *
   * @var \Drupal\identity\Entity\IdentityDataStorage
   */
  protected $storage;

  /**
   * IdentityDataIterator constructor.
   *
   * @param array $ids
   * @param \Drupal\identity\Entity\IdentityDataStorage|NULL $storage
   */
  public function __construct(array $ids, IdentityDataStorage $storage = NULL) {
    $this->ids = array_values($ids);
    $this->entities = array_combine($this->ids, $this->ids);
    $this->storage = $storage;
  }

  /**
   * Get the identity data storage.
   *
   * @return \Drupal\identity\Entity\IdentityDataStorage
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function storage() {
    if (!$this->storage) {
      $this->storage = \Drupal::entityTypeManager()->getStorage('identity_data');
    }

    return $this->storage;
  }

  /**
   * Return the current element
   *
   * @link http://php.net/manual/en/iterator.current.php
   * @return mixed Can return any type.
   * @since 5.0.0
   */
  public function current() {
    $id = $this->ids[$this->current];
    if (is_numeric($this->entities[$id])) {
      $to_load = array_slice($this->ids, $this->current, $this->batchSize);
      $entities = $this->entities;
      $to_load = array_filter($to_load, function($value) use ($entities) {
        return is_numeric($entities[$value]);
      });

      foreach ($this->storage()->loadMultiple($to_load) as $id => $entity) {
        $this->entities[$id] = $entity;
      }
    }
    else if (is_null($id) || $id === FALSE) {
      return NULL;
    }

    return $this->entities[$id];
  }

  /**
   * Move forward to next element
   *
   * @link http://php.net/manual/en/iterator.next.php
   * @return void Any returned value is ignored.
   * @since 5.0.0
   */
  public function next() {
    $this->current++;
  }

  /**
   * Return the key of the current element
   *
   * @link http://php.net/manual/en/iterator.key.php
   * @return mixed scalar on success, or null on failure.
   * @since 5.0.0
   */
  public function key() {
    return $this->ids[$this->current];
  }

  /**
   * Checks if current position is valid
   *
   * @link http://php.net/manual/en/iterator.valid.php
   * @return boolean The return value will be casted to boolean and then evaluated.
   * Returns true on success or false on failure.
   * @since 5.0.0
   */
  public function valid() {
    return isset($this->ids[$this->current]);
  }

  /**
   * Rewind the Iterator to the first element
   *
   * @link http://php.net/manual/en/iterator.rewind.php
   * @return void Any returned value is ignored.
   * @since 5.0.0
   */
  public function rewind() {
    $this->current = 0;
  }

  /**
   * Whether a offset exists
   *
   * @link http://php.net/manual/en/arrayaccess.offsetexists.php
   *
   * @param mixed $offset <p>
   * An offset to check for.
   * </p>
   *
   * @return boolean true on success or false on failure.
   * </p>
   * <p>
   * The return value will be casted to boolean if non-boolean was returned.
   * @since 5.0.0
   */
  public function offsetExists($offset) {
    return isset($this->entities[$offset]);
  }

  /**
   * Offset to retrieve
   *
   * @link http://php.net/manual/en/arrayaccess.offsetget.php
   *
   * @param mixed $offset <p>
   * The offset to retrieve.
   * </p>
   *
   * @return mixed Can return all value types.
   * @since 5.0.0
   */
  public function offsetGet($offset) {
    if (isset($this->entities[$offset]) && is_numeric($this->entities[$offset])) {
      $this->entities[$offset] = $this->storage()->load($offset);
    }

    return $this->entities[$offset];
  }

  /**
   * Offset to set
   *
   * @link http://php.net/manual/en/arrayaccess.offsetset.php
   *
   * @param mixed $offset <p>
   * The offset to assign the value to.
   * </p>
   * @param mixed $value <p>
   * The value to set.
   * </p>
   *
   * @return void
   * @since 5.0.0
   */
  public function offsetSet($offset, $value) {
    if (!($value instanceof IdentityDataInterface) || $value->id() != $offset) {
      throw new \Exception('Illegal given for '.__CLASS__);
    }

    if (!isset($this->entities[$offset])) {
      $this->ids[] = $offset;
    }

    $this->entities[$offset] = $value;
  }

  /**
   * Offset to unset
   *
   * @link http://php.net/manual/en/arrayaccess.offsetunset.php
   *
   * @param mixed $offset <p>
   * The offset to unset.
   * </p>
   *
   * @return void
   * @since 5.0.0
   */
  public function offsetUnset($offset) {
    unset($this->entities[$offset]);

    if ($key = array_search($offset, $this->ids)) {
      unset($this->ids[$key]);
    }
  }
}
