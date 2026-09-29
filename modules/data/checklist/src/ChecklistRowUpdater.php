<?php

namespace Drupal\checklist;

use Drupal\checklist\Ajax\ReconcileRowsCommand;
use Drupal\checklist\Ajax\UpdateItemStateCommand;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Reconciles visible rows with the current checklist without executing items.
 */
class ChecklistRowUpdater {

  /**
   * Constructs the row updater.
   *
   * @param \Drupal\checklist\ChecklistRowBuilder $rowBuilder
   *   Builds the same rows as the initial formatter.
   * @param \Drupal\checklist\ChecklistActionResourcePaneBuilder $paneBuilder
   *   Supplies the checklist's workspace identity.
   */
  public function __construct(
    protected ChecklistRowBuilder $rowBuilder,
    protected ChecklistActionResourcePaneBuilder $paneBuilder,
  ) {}

  /**
   * Updates rows and completion readiness, preserving valid active forms.
   */
  public function refresh(AjaxResponse $response, ChecklistInterface $checklist): void {
    $table = ['#type' => 'table'];
    $states = [];
    foreach ($checklist->getOrderedItems() as $name => $item) {
      if ($row = $this->rowBuilder->build($checklist, $item)) {
        $table[$name] = $row;
        $states[] = new UpdateItemStateCommand($item, $row['#checklist_state']);
      }
    }
    $response->addCommand(new ReconcileRowsCommand(
      '#' . $this->paneBuilder->getWorkspaceId($checklist) . ' table.interactive-checklist',
      $table,
      $checklist->isCompletable()
    ));
    foreach ($states as $state) {
      $response->addCommand($state);
    }
  }

}
