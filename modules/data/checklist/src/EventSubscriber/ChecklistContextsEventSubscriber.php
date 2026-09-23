<?php

namespace Drupal\checklist\EventSubscriber;

use Drupal\checklist\Event\ChecklistCollectContextsEventInterface;
use Drupal\checklist\Event\ChecklistCollectConfigContextsEvent;
use Drupal\checklist\Event\ChecklistEvents;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\typed_data_plus\Plugin\Context\DataContextDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for checklist contexts.
 */
class ChecklistContextsEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ChecklistEvents::COLLECT_RUNTIME_CONTEXTS][] = [
      'addChecklistEntityContext',
      255,
    ];
    $events[ChecklistEvents::COLLECT_CONFIG_CONTEXTS][] = [
      'addChecklistEntityContext',
      255,
    ];
    $events[ChecklistEvents::COLLECT_CONFIG_CONTEXTS][] = [
      'addExpectedItemOutcomes',
      128,
    ];
    $events[ChecklistEvents::COLLECT_RUNTIME_CONTEXTS][] = [
      'addItemOutcomes',
      128,
    ];

    $events[ChecklistEvents::COLLECT_CONFIG_CONTEXTS][] = ['addItemsContext', 0];
    $events[ChecklistEvents::COLLECT_RUNTIME_CONTEXTS][] = ['addItemsContext', 0];
    return $events;
  }

  /**
   * Add the checklist entity as an available context.
   *
   * @param \Drupal\checklist\Event\ChecklistCollectContextsEventInterface $event
   *   The collector event.
   */
  public function addChecklistEntityContext(ChecklistCollectContextsEventInterface $event) {
    $checklist_entity = $event->getChecklist()->getEntity();

    $definition = EntityContextDefinition::create($checklist_entity->getEntityTypeId())
      ->addConstraint('Bundle', $checklist_entity->bundle())
      ->setLabel(new TranslatableMarkup(
        'Checklist @entity_type',
        ['@entity_type' => $checklist_entity->getEntityType()->getLabel()]
      ));
    $event->addContext('checklist:entity', new EntityContext($definition, $event instanceof ChecklistCollectConfigContextsEvent ? NULL : $checklist_entity));
  }

  /**
   * Get contexts from expected outcomes.
   *
   * @param \Drupal\checklist\Event\ChecklistCollectContextsEventInterface $event
   *   The collector event.
   */
  public function addExpectedItemOutcomes(ChecklistCollectContextsEventInterface $event) {
    foreach ($event->getChecklist()->getItems() as $name => $item) {
      $handler = $item->getHandler();
      if (!($handler instanceof ExpectedOutcomeChecklistItemHandlerInterface)) {
        continue;
      }

      foreach ($handler->expectedOutcomeDefinitions() as $outcome_name => $definition) {
        $event->addContext(
          "item:{$name}:{$outcome_name}",
          new Context(DataContextDefinition::fromDataDefinition($definition))
        );
      }
    }
  }

  /**
   * Get the runtime contexts from actual outcomes.
   *
   * @param \Drupal\checklist\Event\ChecklistCollectContextsEventInterface $event
   *   The collector event.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function addItemOutcomes(ChecklistCollectContextsEventInterface $event) {
    foreach ($event->getChecklist()->getItems() as $name => $item) {
      /** @var \Drupal\typed_data_reference\TypedDataReferenceItemList $outcomes */
      $outcomes = $item->get('outcomes');

      foreach ($outcomes->getPropertyDefinitions() as $outcome_name => $definition) {
        $context = new Context(
          DataContextDefinition::fromDataDefinition($definition),
          $outcomes->get($outcome_name)
        );
        $context->addCacheableDependency($item);
        $event->addContext("item:{$name}:{$outcome_name}", $context);
      }
    }
  }

  /**
   * Exposes item statuses and outcomes as a typed tree for condition strings.
   */
  public function addItemsContext(ChecklistCollectContextsEventInterface $event): void {
    $definition = MapDataDefinition::create()->setLabel('Checklist items');
    $values = [];
    $items = $event->getChecklist()->getItems();
    foreach ($items as $name => $item) {
      $outcomes = $item->get('outcomes');
      $outcome_definition = MapDataDefinition::create();
      foreach ($outcomes->getPropertyDefinitions() as $key => $property) {
        $outcome_definition->setPropertyDefinition($key, $property);
      }
      $definition->setPropertyDefinition($name, MapDataDefinition::create()
        ->setPropertyDefinition('status', DataDefinition::create('string'))
        ->setPropertyDefinition('outcomes', $outcome_definition));
      if (!$event instanceof ChecklistCollectConfigContextsEvent) {
        $values[$name] = [
          'status' => $item->get('status')->value,
          'outcomes' => $outcomes->toArray(),
        ];
      }
    }
    $context = new Context(DataContextDefinition::fromDataDefinition($definition), $event instanceof ChecklistCollectConfigContextsEvent ? NULL : $values);
    foreach ($items as $item) {
      $context->addCacheableDependency($item);
    }
    $event->addContext('items', $context);
  }

}
