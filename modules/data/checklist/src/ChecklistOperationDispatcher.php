<?php

namespace Drupal\checklist;

use Drupal\checklist\Plugin\ChecklistItemHandler\ActionOperationsChecklistItemHandlerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Prepares and gates operations for API and tool adapters.
 *
 * Callers supply the current checklist and establish the current account.
 * It does not reload entities, switch accounts, claim work or save results.
 * Handlers own parameter validation, domain effects and persistence.
 */
class ChecklistOperationDispatcher {

  /**
   * Constructs the operation dispatcher.
   *
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   The runtime context preparer.
   */
  public function __construct(protected ChecklistContextPreparer $contextPreparer) {}

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
   */
  public function discover(ChecklistInterface $checklist, string $item_name): array {
    if (!$checklist->getEntity()->access('update')) {
      return [];
    }
    return $this->prepareHandler($checklist, $item_name)?->actionOperations() ?? [];
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
   * @param array $parameters
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
   */
  public function execute(ChecklistInterface $checklist, string $item_name, string $operation, array $parameters): array {
    if (!$checklist->getEntity()->access('update')) {
      throw new AccessDeniedHttpException('The checklist cannot be updated.');
    }
    $handler = $this->prepareHandler($checklist, $item_name);
    if (!$handler) {
      throw new \DomainException('The checklist item cannot accept operations.');
    }
    if (!array_key_exists($operation, $handler->actionOperations())) {
      throw new \InvalidArgumentException('The checklist operation is not available.');
    }
    return $handler->executeActionOperation($operation, $parameters);
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
    if (!$this->contextPreparer->prepare($checklist, $item) || $item->isApplicable() !== TRUE || !$item->isActionable()) {
      return NULL;
    }
    return $handler;
  }

}
