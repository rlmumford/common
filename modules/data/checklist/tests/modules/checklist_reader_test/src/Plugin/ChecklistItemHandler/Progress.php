<?php

namespace Drupal\checklist_reader_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\ChecklistActionState;
use Drupal\checklist\ChecklistActionResource;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionResourceChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ContextAwareChecklistItemHandlerBase;

/**
 * Projects fixture state without executing work.
 *
 * @ChecklistItemHandler(
 *   id = "reader_progress",
 *   label = @Translation("Reader progress"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("string", required = TRUE)
 *   }
 * )
 */
class Progress extends ContextAwareChecklistItemHandlerBase implements ActionStateChecklistItemHandlerInterface, ActionOperationsChecklistItemHandlerInterface, ActionResourceChecklistItemHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getMethod(): string {
    return ChecklistItemInterface::METHOD_AUTO;
  }

  /**
   * {@inheritdoc}
   */
  public function action(): ChecklistItemHandlerInterface {
    throw new \LogicException('Reading an item must not run its action.');
  }

  /**
   * {@inheritdoc}
   */
  public function getActionState(): ?ChecklistActionState {
    if (!empty($this->configuration['fail_on_read'])) {
      throw new \LogicException('Inaccessible progress must not be evaluated.');
    }
    $state = \Drupal::state()->get('checklist_reader_test.progress', []);
    return new ChecklistActionState(
      stage: $state['stage'] ?? 'running',
      message: 'Processing ' . $this->getContextValue('value'),
      completed: $state['completed'] ?? 2,
      total: $state['total'] ?? 5,
      updatedAt: $state['updated_at'] ?? 1234567890,
      inputRequired: $state['input_required'] ?? FALSE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    return ['continue' => ['label' => 'Continue', 'parameters_schema' => ['type' => 'object']]];
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    throw new \LogicException('Inaccessible work must not execute.');
  }

  /**
   * {@inheritdoc}
   */
  public function getActionResource(): ?ChecklistActionResource {
    if (empty($this->configuration['resource_key'])) {
      return NULL;
    }
    return new ChecklistActionResource(
      $this->configuration['resource_key'],
      ['#plain_text' => $this->configuration['resource_content'] ?? 'Context value: ' . $this->getContextValue('value')],
      $this->configuration['resource_label'] ?? NULL,
      $this->configuration['resource_weight'] ?? 0,
    );
  }

}
