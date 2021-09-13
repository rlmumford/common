<?php

namespace Drupal\task\Event;

/**
 * Define task event names.
 */
final class TaskEvents {

  /**
   * Name of the event fired when assigning a task.
   *
   * @Event
   */
  const SELECT_ASSIGNEE = 'task.select_assignee';

}
