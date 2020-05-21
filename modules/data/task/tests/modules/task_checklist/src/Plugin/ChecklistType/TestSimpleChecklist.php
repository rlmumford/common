<?php

namespace Drupal\task_checklist_test\Plugin\ChecklistType;

use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase;

/**
 * Class TestSimpleChecklist
 *
 * @ChecklistType(
 *   id = "test_simple_task",
 *   label = "Test Simple Task Checklist",
 *   entity_type = "task",
 * )
 *
 * @package Drupal\task_checklist_test\Plugin\ChecklistType
 */
class TestSimpleChecklist extends ChecklistTypeBase {

  /**
   * Get the default items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface[]
   */
  public function getDefaultItems(): array {
    $items = [
      'AC01' => [
        'name' => 'AC01',
        'label' => 'First Item on the Checklist',
        'handler' => 'simply_checkable',
        'handler_configuration' => [],
      ],
      'AC02' => [
        'name' => 'AC02',
        'label' => 'Second Item on the Checklist',
        'handler' => 'simply_checkable',
        'handler_configuration' => [],
      ]
    ];
    return $items;
  }

}
