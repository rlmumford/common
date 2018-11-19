<?php
/**
 * Created by PhpStorm.
 * User: Rob
 * Date: 19/11/2018
 * Time: 12:12
 */

namespace Drupal\task\Event;

use Drupal\task\Entity\Task;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class SelectAssigneeEvent
 *
 * @package Drupal\task\Event
 */
class SelectAssigneeEvent extends Event {

  /**
   * @var \Drupal\task\Entity\Task
   */
  protected $task;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $assignee;

  /**
   * SelectAssigneeEvent constructor.
   *
   * @param \Drupal\task\Entity\Task $task
   */
  public function __construct(Task $task) {
    $this->task = $task;
  }

  /**
   * @return \Drupal\task\Entity\Task
   */
  public function getTask() {
    return $this->task;
  }

  /**
   * Set the assignee.
   */
  public function setAssignee(User $assignee) {
    $this->assignee = $assignee;
  }

  /**
   * Get the assignee currently selected.
   *
   * @return \Drupal\user\Entity\User
   */
  public function getAssignee() {
    return $this->assignee;
  }
}
