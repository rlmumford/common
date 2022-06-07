<?php

namespace Drupal\checklist\EventSubscriber;

use Drupal\checklist\Event\ChecklistCollectContextsEventInterface;
use Drupal\checklist\Event\ChecklistEvents;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\typed_data_reference\TypedDataDefinitionToContextDefinitionTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for checklist contexts.
 */
class ChecklistContextsEventSubscriber implements EventSubscriberInterface {
  use TypedDataDefinitionToContextDefinitionTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
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
    $event->addContext('checklist:entity', new EntityContext($definition, $checklist_entity));
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
          new Context($this->contextDefinitionForDataDefinition($definition))
        );
      }
    }
  }

  /**
   * Get the runtime contexts from actual outcomes.
   */
  public function addItemOutcomes(ChecklistCollectContextsEventInterface $event) {
    foreach ($event->getChecklist()->getItems() as $name => $item) {
      /** @var \Drupal\typed_data_reference\TypedDataReferenceItemList $outcomes */
      $outcomes = $item->get('outcomes');

      foreach ($outcomes->getPropertyDefinitions() as $outcome_name => $definition) {
        $event->addContext(
          "item:{$name}:{$outcome_name}",
          new Context(
            $this->contextDefinitionForDataDefinition($definition),
            $outcomes->get($outcome_name)->getValue()
          )
        );
      }
    }
  }

}
