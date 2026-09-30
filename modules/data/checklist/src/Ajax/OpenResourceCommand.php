<?php

namespace Drupal\checklist\Ajax;

use Drupal\Core\Ajax\InsertCommand;

/**
 * Opens a resource without replacing other resources or their working forms.
 */
class OpenResourceCommand extends InsertCommand {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $command = parent::render();
    $command['command'] = 'checklistOpenResource';
    return $command;
  }

}
