<?php

namespace Drupal\task;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\task\Event\SelectAssigneeEvent;
use Drupal\task\Event\TaskEvents;

class TaskStorage extends SqlContentEntityStorage {

  public function doPreSave(EntityInterface $entity) {
    $id = parent::doPreSave($entity);

    if (!$entity->assignee->entity) {
      $event = new SelectAssigneeEvent($entity);

      /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
      $dispatcher = \Drupal::service('event_dispatcher');
      $dispatcher->dispatch(TaskEvents::SELECT_ASSIGNEE, $event);

      if ($event->getAssignee()) {
        $entity->assignee = $event->getAssignee();
      }
    }

    return $id;
  }
}
