<?php

namespace Drupal\checklist;

use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Execution\ChecklistItemExecutor;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Processes a whole checklist, refreshing readiness as items produce results.
 */
class ChecklistProcessor {

  /**
   * Constructs the checklist processor.
   *
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   Refreshes contexts for existing synchronous handlers.
   * @param \Drupal\checklist\Execution\ChecklistItemExecutor $executor
   *   The common audited inline/worker execution path for individual items.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Reloads persisted item results into the checklist's context graph.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Measures the remaining request budget.
   */
  public function __construct(
    protected ChecklistContextPreparer $contextPreparer,
    protected ChecklistItemExecutor $executor,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {}

  /**
   * Runs ready work within a budget, then evaluates checklist completion.
   *
   * Each item is invoked at most once in this call. Blocked items are revisited
   * after progress, so an earlier item can consume a later item's new outcome.
   * Waiting attempts continue through workers, not an inline polling loop.
   * The budget controls starting another item; it cannot interrupt a handler.
   * Consumers can use the same processor in a web request or a PHP worker.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The authoritative checklist graph to process as the current caller.
   * @param float $budget_seconds
   *   Nonnegative finite inline budget; zero defers all supported ready items.
   *
   * @return bool|null
   *   Completion readiness, or NULL for an empty checklist.
   */
  public function process(ChecklistInterface $checklist, float $budget_seconds = 10): ?bool {
    if (!is_finite($budget_seconds) || $budget_seconds < 0) {
      throw new \InvalidArgumentException('The checklist budget must be finite and nonnegative.');
    }
    if (!$checklist->getOrderedItems()) {
      return NULL;
    }
    $deadline = $this->time->getCurrentMicroTime() + $budget_seconds;
    $visited = [];
    do {
      $progress = FALSE;
      foreach ($checklist->getOrderedItems() as $name => $item) {
        if (isset($visited[$name]) || $item->isComplete() || $item->get('status')->value === ChecklistItemInterface::STATUS_NA) {
          continue;
        }
        $before = $item->toArray();
        $defer = $this->time->getCurrentMicroTime() >= $deadline;
        if ($item->getHandler() instanceof IterativeChecklistItemHandlerInterface) {
          if ($item->getMethod() !== ChecklistItemInterface::METHOD_AUTO || $checklist->getEntity()->isNew() || $item->isNew()) {
            continue;
          }
          $attempt = $this->executor->submit($item, $defer);
          if (!$attempt) {
            continue;
          }
          $visited[$name] = TRUE;
          $fresh = $this->entityTypeManager->getStorage('checklist_item')->loadUnchanged($item->id());
          $fresh->get('checklist')->entity = $checklist->getEntity();
          $checklist->setItem($name, $fresh);
          $progress = $progress || $before !== $fresh->toArray();
          continue;
        }
        // Legacy synchronous handlers retain their existing action contract.
        // They cannot safely be put on the result-based worker path implicitly.
        if ($defer || !$this->contextPreparer->prepare($checklist, $item) || $item->isApplicable() !== TRUE || $item->getMethod() !== ChecklistItemInterface::METHOD_AUTO || !$item->isActionable()) {
          continue;
        }
        $visited[$name] = TRUE;
        try {
          $item->action();
          if (!$item->isComplete()) {
            $item->setAttempted();
          }
        }
        catch (\Exception) {
          $item->setFailed(ChecklistItemInterface::METHOD_AUTO);
        }
        $item->save();
        $progress = $progress || $before !== $item->toArray();
      }
    } while ($progress);

    $resolvable = $checklist->isCompletable();
    if ($resolvable) {
      $checklist->complete();
    }
    return $resolvable;
  }

}
