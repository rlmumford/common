<?php

namespace Drupal\task\Entity;

interface TaskPlanInterface {

  /**
   * Create a new task for this task plan.
   *
   * @return \Drupal\task\Entity\Task
   */
  public function createTask();

}
