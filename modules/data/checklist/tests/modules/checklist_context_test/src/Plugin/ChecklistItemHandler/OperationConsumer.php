<?php

namespace Drupal\checklist_context_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;

/**
 * Exposes contexts through operations, relying on dispatcher preparation.
 *
 * @ChecklistItemHandler(
 *   id = "operation_consumer",
 *   label = @Translation("Operation consumer"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("string", required = TRUE),
 *     "optional" = @ContextDefinition("string", required = FALSE)
 *   }
 * )
 */
class OperationConsumer extends Consumer implements ActionOperationsChecklistItemHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function actionOperations(): array {
    if ($this->getContextValue('value') === 'Hidden') {
      return [];
    }
    return [
      'read' => [
        'label' => $this->getContextValue('value'),
        'description' => 'Read the assigned contexts.',
        'parameters_schema' => ['type' => 'object', 'additionalProperties' => FALSE],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeActionOperation(string $operation, array $parameters): array {
    if ($parameters) {
      throw new \InvalidArgumentException('No parameters expected.');
    }
    $this->action();
    return ['value' => $this->getContextValue('value'), 'optional' => $this->getContextValue('optional')];
  }

}
