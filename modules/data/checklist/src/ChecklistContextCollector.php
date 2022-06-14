<?php

namespace Drupal\checklist;

use Drupal\checklist\Event\ChecklistCollectConfigContextsEvent;
use Drupal\checklist\Event\ChecklistCollectRuntimeContextsEvent;
use Drupal\checklist\Event\ChecklistEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service to help collect contexts available for a particular checklist.
 */
class ChecklistContextCollector implements ChecklistContextCollectorInterface {

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Construct a checklist context collector.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function collectConfigContexts(ChecklistInterface $checklist, array $config_context = []) : array {
    $event = new ChecklistCollectConfigContextsEvent($checklist, $config_context);
    $this->eventDispatcher->dispatch($event, ChecklistEvents::COLLECT_CONFIG_CONTEXTS);
    return $event->getContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function collectRuntimeContexts(ChecklistInterface $checklist): array {
    $event = new ChecklistCollectRuntimeContextsEvent($checklist);
    $this->eventDispatcher->dispatch($event, ChecklistEvents::COLLECT_RUNTIME_CONTEXTS);
    return $event->getContexts();
  }

}
