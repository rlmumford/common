<?php

namespace Drupal\checklist;

use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Prepares and gates operations for API and tool adapters.
 *
 * Callers supply the current checklist and establish the current account.
 * It does not reload entities, switch accounts, claim work or save results.
 * Handlers own domain validation, effects and persistence; the dispatcher
 * enforces the schemas advertised by each operation.
 */
class ChecklistActionOperationDispatcher {

  /**
   * Constructs the operation dispatcher.
   *
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   The runtime context preparer.
   * @param \Drupal\checklist\ChecklistOperationSchemaValidator $schemaValidator
   *   The operation schema validator.
   */
  public function __construct(
    protected ChecklistContextPreparer $contextPreparer,
    protected ChecklistOperationSchemaValidator $schemaValidator,
  ) {}

  /**
   * Describes the operations currently available to the current account.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The current checklist.
   * @param string $item_name
   *   The item's name within this checklist.
   *
   * @return array
   *   Operation definitions keyed by name, or an empty array when inaccessible,
   *   blocked, finished or unsupported. This snapshot is not authorization.
   *
   * @throws \InvalidArgumentException
   *   When the named item does not exist on an accessible checklist.
   * @throws \UnexpectedValueException
   *   When an operation definition violates the schema contract.
   */
  public function discover(
    ChecklistInterface $checklist,
    string $item_name,
  ): array {
    if (!$checklist->getEntity()->access('update')) {
      return [];
    }
    $handler = $this->prepareHandler($checklist, $item_name);
    return $handler ? $this->prepareOperations($handler->actionOperations()) : [];
  }

  /**
   * Executes an operation after fresh context assignment and gate checks.
   *
   * Transport authentication and CSRF checks belong to the calling adapter.
   * Configuration errors and handler exceptions propagate to the caller.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The current checklist.
   * @param string $item_name
   *   The item's name within this checklist.
   * @param string $operation
   *   The currently advertised operation name.
   * @param array|\stdClass $parameters
   *   Parameters to validate and execute in the handler.
   *
   * @return array
   *   The handler's structured result.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the current account cannot update the containing entity.
   * @throws \DomainException
   *   When the item cannot currently accept operations.
   * @throws \InvalidArgumentException
   *   When the item or advertised operation does not exist.
   * @throws \Drupal\checklist\ChecklistOperationInputException
   *   When parameters do not match the operation schema.
   * @throws \UnexpectedValueException
   *   When an operation schema or result violates its contract.
   */
  public function execute(
    ChecklistInterface $checklist,
    string $item_name,
    string $operation,
    array|\stdClass $parameters,
  ): array {
    if (!$checklist->getEntity()->access('update')) {
      throw new AccessDeniedHttpException('The checklist cannot be updated.');
    }
    $handler = $this->prepareHandler($checklist, $item_name);
    if (!$handler) {
      throw new \DomainException('The checklist item cannot accept operations.');
    }
    $operations = $this->prepareOperations($handler->actionOperations());
    if (!array_key_exists($operation, $operations)) {
      throw new \InvalidArgumentException('The checklist operation is not available.');
    }
    $definition = $operations[$operation];
    $this->schemaValidator->validateInput(
      $parameters,
      $definition['parameters_schema'],
    );
    $result = $handler->executeActionOperation(
      $operation,
      $this->schemaValidator->toArray($parameters),
    );
    if (isset($definition['result_schema'])) {
      $this->schemaValidator->validateResult($result, $definition['result_schema']);
    }
    return $result;
  }

  /**
   * Normalizes operation schemas and declares their dialect for clients.
   *
   * @param array $operations
   *   Operation definitions keyed by operation name.
   *
   * @return array
   *   Definitions with explicit parameter and result schema dialects.
   */
  protected function prepareOperations(array $operations): array {
    foreach ($operations as $name => &$definition) {
      if (
        !is_array($definition)
        || !isset($definition['parameters_schema'])
        || !is_array($definition['parameters_schema'])
      ) {
        throw new \UnexpectedValueException(sprintf(
          'Checklist operation "%s" must define a parameter schema.',
          $name,
        ));
      }
      if (($definition['parameters_schema']['type'] ?? NULL) !== 'object') {
        throw new \UnexpectedValueException(sprintf(
          'Checklist operation "%s" parameter schema must have an object at its root.',
          $name,
        ));
      }
      $definition['parameters_schema'] = $this->schemaValidator->exposeSchema(
        $definition['parameters_schema'],
      );
      if (array_key_exists('result_schema', $definition)) {
        if (!is_array($definition['result_schema'])) {
          throw new \UnexpectedValueException(sprintf(
            'Checklist operation "%s" has an invalid result schema.',
            $name,
          ));
        }
        $definition['result_schema'] = $this->schemaValidator->exposeSchema(
          $definition['result_schema'],
        );
      }
    }
    unset($definition);
    return $operations;
  }

  /**
   * Gets an operation handler with current contexts and satisfied item gates.
   */
  protected function prepareHandler(ChecklistInterface $checklist, string $item_name): ?ActionOperationsChecklistItemHandlerInterface {
    if (!$checklist->hasItem($item_name)) {
      throw new \InvalidArgumentException('The checklist item does not exist.');
    }
    $item = $checklist->getItem($item_name);
    $handler = $item->getHandler();
    if (!$handler instanceof ActionOperationsChecklistItemHandlerInterface || !$item->isIncomplete()) {
      return NULL;
    }
    if (!$item->access('view action state') || !$item->access('execute action operation')) {
      return NULL;
    }
    if (!$this->contextPreparer->prepare($checklist, $item) || $item->isApplicable() !== TRUE || !$item->isActionable()) {
      return NULL;
    }
    return $handler;
  }

}
