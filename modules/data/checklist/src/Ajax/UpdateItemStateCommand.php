<?php

namespace Drupal\checklist\Ajax;

use Drupal\checklist\Entity\ChecklistItemInterface;

/**
 * Synchronizes an existing row without replacing its active action form.
 */
class UpdateItemStateCommand extends ChecklistItemCommand {

  /**
   * Constructs the state command.
   *
   * @param \Drupal\checklist\Entity\ChecklistItemInterface $item
   *   The item being projected.
   * @param array $state
   *   Current visibility, context, status and readiness flags.
   */
  public function __construct(ChecklistItemInterface $item, protected array $state) {
    parent::__construct($item, NULL, 'checklistItemState');
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return parent::render() + ['state' => $this->state];
  }

}
