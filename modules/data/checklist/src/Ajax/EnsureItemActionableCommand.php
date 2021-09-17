<?php

namespace Drupal\checklist\Ajax;

/**
 * Command to ensure the checklist item is actionable.
 */
class EnsureItemActionableCommand extends ChecklistItemCommand {

  /**
   * {@inheritdoc}
   */
  protected $command = 'itemEnsureActionable';

}
