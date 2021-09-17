<?php

namespace Drupal\checklist\Ajax;

/**
 * Command to ensure the checklist item is marked as failed.
 */
class EnsureItemFailedCommand extends ChecklistItemCommand {

  /**
   * {@inheritdoc}
   */
  protected $command = 'itemEnsureFailed';

}
