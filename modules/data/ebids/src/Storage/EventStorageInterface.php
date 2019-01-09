<?php

namespace Drupal\ebids\Storage;

use Drupal\ebids\EventInterface;

interface EventStorageInterface {

  /**
   * Record an event in this storage.
   *
   * @param \Drupal\ebids\EventInterface $event
   *
   * @return mixed
   */
  public function recordEvent(EventInterface $event);

  /**
   * Read a specific event in this storage.
   *
   * @param string $id
   *   The uuid of the event to return.
   * @param string $return_class
   *   The class of object to return, must implement EventInterface.
   *
   * @return \Drupal\ebids\EventInterface
   */
  public function readEvent($id, $return_class = NULL);

  /**
   * Read a set of specific events
   *
   * @param string[] $ids
   *   The ids of the events to load.
   * @param string $return_class
   *   The class of objects to return, must implement EventInterface.
   *
   * @return \Drupal\ebids\EventInterface[]
   */
  public function readEvents(array $ids = array(), $return_class = NULL);

  /**
   * Find events based on a query.
   *
   * @param array $query
   *   The query to filter events by.
   * @param string $return_class
   *   The class of objects to return must implement EventInterface.
   *
   * @return \Drupal\ebids\EventInterface[]
   */
  public function findEvents(array $query, $return_class = NULL);

}
