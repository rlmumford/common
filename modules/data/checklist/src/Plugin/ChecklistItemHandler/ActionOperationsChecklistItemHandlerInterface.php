<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

/**
 * Exposes structured operations for API and tool adapters.
 *
 * Adapters use checklist.action_operation_dispatcher for runtime contexts,
 * shared access and item gates. The dispatcher validates JSON Schema
 * contracts before and after execution. Implementations still validate domain
 * rules and recheck operation-specific permissions and availability.
 * Transport authentication, CSRF protection and identity belong to the
 * adapter. Discovery is a snapshot, never authorization to execute.
 */
interface ActionOperationsChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Describes currently available operations.
   *
   * @return array
   *   Definitions keyed by name, with label, description and parameters_schema
   *   (JSON Schema Draft 7). result_schema (also Draft 7) may describe the
   *   structured result. An empty array means no operation is currently
   *   available.
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
   *   The structured result, which is validated when result_schema is defined.
   */
  public function executeActionOperation(string $operation, array $parameters): array;

}
