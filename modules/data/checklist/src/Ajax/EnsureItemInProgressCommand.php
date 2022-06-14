<?php

namespace Drupal\checklist\Ajax;

/**
 * Command to ensure an item is in progress.
 */
class EnsureItemInProgressCommand extends ChecklistItemCommand {

  /**
   * {@inheritdoc}
   */
  protected $command = 'itemEnsureInProgress';

}
