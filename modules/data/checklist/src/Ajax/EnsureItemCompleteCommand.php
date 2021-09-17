<?php

namespace Drupal\checklist\Ajax;

/**
 * Command to ensure the checklist item is complete.
 */
class EnsureItemCompleteCommand extends ChecklistItemCommand {

  /**
   * {@inheritdoc}
   */
  protected $command = 'itemEnsureComplete';

}
