<?php

namespace Drupal\checklist_state_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\ChecklistActionState;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerBase;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ExpectedOutcomeChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\StatefulChecklistItemHandlerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Exercises durable state separately from published outcomes.
 *
 * @ChecklistItemHandler(
 *   id = "state_test",
 *   label = @Translation("State test")
 * )
 */
class Stateful extends ChecklistItemHandlerBase implements StatefulChecklistItemHandlerInterface, ExpectedOutcomeChecklistItemHandlerInterface, ActionStateChecklistItemHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function stateDefinitions(): array {
    return [
      'run_id' => DataDefinition::create('string'),
      'completed' => DataDefinition::create('integer'),
      'steps' => ListDataDefinition::create('string'),
      'details' => MapDataDefinition::create()->setPropertyDefinition('label', DataDefinition::create('string')),
      'owner' => EntityDataDefinition::create('user'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function expectedOutcomeDefinitions(): array {
    return ['result' => DataDefinition::create('string')];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_MANUAL;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getActionState(): ?ChecklistActionState {
    return new ChecklistActionState(
      stage: 'working',
      message: 'Processing',
      completed: $this->getItem()->get('state')->get('completed')->getValue(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): array {
    return [];
  }

}
