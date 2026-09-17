<?php

namespace Drupal\checklist_context_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Produces outcomes for context mapping tests.
 *
 * @ChecklistItemHandler(
 *   id = "context_producer",
 *   label = @Translation("Context producer")
 * )
 */
class Producer extends ChecklistItemHandlerBase implements ExpectedOutcomeChecklistItemHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return [
      'value' => DataDefinition::create('string')->setLabel('Value'),
      'details' => MapDataDefinition::create()
        ->setLabel('Details')
        ->setPropertyDefinition('label', DataDefinition::create('string')),
      'tags' => ListDataDefinition::create('string')->setLabel('Tags'),
      'user' => EntityDataDefinition::create('user')->setLabel('User'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return $this->configuration['method'] ?? ChecklistItemInterface::METHOD_AUTO;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    $this->item->setOutcome('value', 'Produced');
    $this->item->setComplete(ChecklistItemInterface::METHOD_AUTO);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return [];
  }

}
