<?php

namespace Drupal\checklist;

use Drupal\checklist\Plugin\ChecklistItemHandler\ActionResourceChecklistItemHandlerInterface;

/**
 * Collects item resources for a checklist's contextual resource pane.
 */
class ChecklistActionResourceCollector implements ChecklistActionResourceCollectorInterface {

  /**
   * Constructs the collector.
   *
   * @param \Drupal\checklist\ChecklistContextPreparer $contextPreparer
   *   Prepares current contexts before gates and resources are evaluated.
   */
  public function __construct(
    protected ChecklistContextPreparer $contextPreparer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function collect(ChecklistInterface $checklist): array {
    $resources = [];
    foreach ($checklist->getOrderedItems() as $name => $item) {
      // This operation is shared with the read-only item API, so an item that
      // is hidden from that viewer cannot disclose its resource in the UI.
      if (!$item->access('view action state')) {
        continue;
      }

      $contexts_available = $this->contextPreparer->prepare($checklist, $item);
      $terminal = $item->isComplete() || $item->isFailed();
      if (!$terminal) {
        if (!$contexts_available
          || $item->isApplicable() !== TRUE
          || !$item->isActionable()) {
          continue;
        }
      }

      $handler = $item->getHandler();
      if (!$handler instanceof ActionResourceChecklistItemHandlerInterface) {
        continue;
      }
      $resource = $handler->getActionResource();
      if (!$resource) {
        continue;
      }

      if (!isset($resources[$resource->getKey()])) {
        $resources[$resource->getKey()] = [
          'resource' => $resource,
          'owners' => [],
        ];
      }
      else {
        // A shared pane is a live view of its most recently ordered eligible
        // producer while retaining all items that refer to it.
        $resources[$resource->getKey()]['resource'] = $resource;
      }
      $resources[$resource->getKey()]['owners'][] = $name;
    }
    return $resources;
  }

}
