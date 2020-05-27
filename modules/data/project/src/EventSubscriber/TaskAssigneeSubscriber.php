<?php

namespace Drupal\project\EventSubscriber;

use Drupal\task\Event\SelectAssigneeEvent;
use Drupal\task\Event\TaskEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TaskAssigneeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[TaskEvents::SELECT_ASSIGNEE][] = ['onAssigneeSelect'];
    return $events;
  }

  /**
   * React to assignee selection.
   *
   * @param \Drupal\task\Event\SelectAssigneeEvent $event
   */
  public function onAssigneeSelect(SelectAssigneeEvent $event) {
    $task = $event->getTask();

    if ($task->project->entity && $task->project->entity->manager->entity) {
      $event->setAssignee($task->project->entity->manager->entity);
    }
  }
}
