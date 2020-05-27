<?php

namespace Drupal\checklist\EventSubscriber;

use Drupal\checklist\Event\ChecklistItemEvent;
use Drupal\checklist\Event\ChecklistItemEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ChecklistItemEventMessageSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $messageStorage;

  /**
   * ChecklistItemEventMessageSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->messageStorage = $entity_type_manager->getStorage('message');
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * ['eventName' => 'methodName']
   *  * ['eventName' => ['methodName', $priority]]
   *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
   *
   * @return array The event names to listen to
   */
  public static function getSubscribedEvents() {
    return [
      ChecklistItemEvents::ITEM_COMPLETED => 'onItemCompleted',
    ];
  }

  /**
   * Create a log message when an item is completed.
   *
   * @param \Drupal\checklist\Event\ChecklistItemEvent $event
   */
  public function onItemCompleted(ChecklistItemEvent $event) {
   $message = $this->messageStorage->create([
      'template' => 'checklist_item_event',
      'checklist_item' => $event->getChecklistItem(),
      'checklist_item_event' => 'completed',
    ]);

   if ($event->getChecklistItem()->checklist->entity->getEntityTypeId() == 'task') {
     $message->task = $event->getChecklistItem()->checklist->entity;
   }

   $message->save();
  }
}
