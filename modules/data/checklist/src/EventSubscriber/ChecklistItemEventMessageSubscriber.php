<?php

namespace Drupal\checklist\EventSubscriber;

use Drupal\checklist\Event\ChecklistItemEvent;
use Drupal\checklist\Event\ChecklistItemEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to checklist events on behalf of the message module.
 */
class ChecklistItemEventMessageSubscriber implements EventSubscriberInterface {

  /**
   * Message storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $messageStorage;

  /**
   * ChecklistItemEventMessageSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->messageStorage = $entity_type_manager->getStorage('message');
  }

  /**
   * {@inheritdoc}
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
   *   The checklist item event.
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
