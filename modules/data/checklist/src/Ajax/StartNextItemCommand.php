<?php

namespace Drupal\checklist\Ajax;

/**
 * Command to start the next checklist item.
 */
class StartNextItemCommand extends ChecklistItemCommand {

  /**
   * {@inheritdoc}
   */
  protected $command = 'startNextItem';

}
