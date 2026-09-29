<?php

namespace Drupal\checklist;

use Drupal\checklist\Attempt\ChecklistAttemptJournal;
use Drupal\checklist\Entity\ChecklistItemInterface;
use Drupal\checklist\Plugin\ChecklistItemHandler\ActionStateChecklistItemHandlerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads authorized item snapshots without executing checklist work.
 *
 * Snapshots are request-local and must not be cached across users or changes.
 * HTTP adapters must disable response caching until metadata is aggregated.
 */
class ChecklistItemReader {

  /**
   * Constructs the item reader.
   *
   * @param \Drupal\checklist\ChecklistResolver $resolver
   *   The entity-based checklist resolver.
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   The runtime context preparer.
   * @param \Drupal\checklist\Attempt\ChecklistAttemptJournal $journal
   *   The internal attempt journal.
   */
  public function __construct(
    protected ChecklistResolver $resolver,
    protected ChecklistContextPreparer $contextPreparer,
    protected ChecklistAttemptJournal $journal,
  ) {}

  /**
   * Reads one item from a checklist on the supplied host entity.
   *
   * Missing and hidden items both produce a not-found result. Host and field
   * access failures propagate from the resolver before looking up the item.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The current host entity, including unsaved state when appropriate.
   * @param string $field_name
   *   The checklist field name.
   * @param int $delta
   *   The checklist field delta.
   * @param string $item_name
   *   The item name within this checklist.
   *
   * @return array
   *   Identity, status, gates and optional action progress as plain data.
   */
  public function read(FieldableEntityInterface $entity, string $field_name, int $delta, string $item_name): array {
    $checklist = $this->resolver->resolve($entity, $field_name, $delta);
    if (!$checklist->hasItem($item_name) || !$checklist->getItem($item_name)->access('view action state')) {
      throw new NotFoundHttpException('Checklist item not found.');
    }
    return $this->snapshot($checklist, $checklist->getItem($item_name));
  }

  /**
   * Reads visible items, keyed by their names within the checklist.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The current host entity.
   * @param string $field_name
   *   The checklist field name.
   * @param int $delta
   *   The checklist field delta.
   *
   * @return array
   *   Visible item snapshots. Hidden items are omitted entirely.
   */
  public function readItems(FieldableEntityInterface $entity, string $field_name, int $delta = 0): array {
    $checklist = $this->resolver->resolve($entity, $field_name, $delta);
    $items = [];
    foreach ($checklist->getOrderedItems() as $name => $item) {
      if ($item->access('view action state')) {
        $items[$name] = $this->snapshot($checklist, $item);
      }
    }
    return $items;
  }

  /**
   * Reads a bounded page of authorized execution history.
   *
   * A NULL attempt selects the latest; clients should pin its returned ID when
   * paging. Previous attempt IDs allow traversal without an unbounded query.
   * Item visibility is checked before journal lookup. No handler is invoked.
   * Only explicitly selected audit metadata is exposed, never working state or
   * internal execution/claim data. Reasons must follow the journal's safe-text
   * contract; consumers must render them as plain text.
   */
  public function readHistory(FieldableEntityInterface $entity, string $field_name, int $delta, string $item_name, ?string $attempt_id = NULL, int $after_version = 0, int $limit = 50): array {
    $checklist = $this->resolver->resolve($entity, $field_name, $delta);
    if (!$checklist->hasItem($item_name) || !$checklist->getItem($item_name)->access('view action state')) {
      throw new NotFoundHttpException('Checklist item not found.');
    }
    if ($after_version < 0 || $limit < 1 || $limit > 100) {
      throw new \InvalidArgumentException('History needs a nonnegative cursor and a limit between 1 and 100.');
    }
    $item = $checklist->getItem($item_name);
    $attempt = $attempt_id === NULL ? $this->journal->latest($item) : $this->journal->load($attempt_id);
    if ($attempt_id !== NULL && (!$attempt || $attempt->itemUuid !== $item->uuid())) {
      throw new NotFoundHttpException('Checklist attempt not found.');
    }
    $events = $attempt ? $this->journal->history($attempt->id, $after_version, $limit) : [];
    return [
      'item' => $item_name,
      'attempt' => $attempt ? [
        'id' => $attempt->id,
        'previous' => $attempt->previous,
        'mode' => $attempt->mode,
        'status' => $attempt->status,
        'version' => $attempt->version,
        'initiator' => $attempt->initiator,
        'executor' => $attempt->executor,
        'path' => $attempt->path,
        'operation' => $attempt->operation,
        'created' => $attempt->created,
        'changed' => $attempt->changed,
      ] : NULL,
      'events' => $events,
      'next_after_version' => $events ? end($events)['version'] : $after_version,
    ];
  }

  /**
   * Projects a visible item's current gates and optional handler progress.
   */
  protected function snapshot(ChecklistInterface $checklist, ChecklistItemInterface $item): array {
    $contexts_available = $this->contextPreparer->prepare($checklist, $item);
    $applicable = $contexts_available ? $item->isApplicable() : NULL;
    $handler = $item->getHandler();
    $action_state = $contexts_available && $handler instanceof ActionStateChecklistItemHandlerInterface
      ? $handler->getActionState()?->toArray()
      : NULL;
    return [
      'name' => $item->getName(),
      'title' => $item->get('title')->value,
      'status' => $item->get('status')->value,
      'contexts_available' => $contexts_available,
      'applicable' => $applicable,
      'required' => $contexts_available ? $item->isRequired() : TRUE,
      'actionable' => $item->isIncomplete() && $contexts_available && $applicable === TRUE && $item->isActionable(),
      'action_state' => $action_state,
    ];
  }

}
