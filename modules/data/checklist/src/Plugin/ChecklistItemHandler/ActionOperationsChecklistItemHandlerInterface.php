<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

/**
 * Exposes structured operations for API and tool adapters.
 *
 * Implementations validate inputs and recheck access and gates on execution.
 * Transport authentication, CSRF protection and identity belong to the
 * adapter. Discovery is a snapshot, never authorization to execute.
 */
interface ActionOperationsChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Describes currently available operations.
   *
   * @return array
   *   Definitions keyed by name, with label, description and parameters_schema
   *   (JSON Schema). An empty array means no operation is currently available.
   */
  public function actionOperations(): array;

  /**
   * Executes an operation with current access and input validation.
   *
   * @param string $operation
   *   The operation name.
   * @param array $parameters
   *   The operation parameters.
   *
   * @return array
   *   The structured result. Implementations document their result shape.
   */
  public function executeActionOperation(string $operation, array $parameters): array;

}
