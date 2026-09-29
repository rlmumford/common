<?php

namespace Drupal\checklist\Ajax;

use Drupal\Core\Ajax\InsertCommand;

/**
 * Reconciles rendered checklist rows while retaining active action forms.
 */
class ReconcileRowsCommand extends InsertCommand {

  /**
   * Constructs the reconciliation command.
   *
   * @param string $selector
   *   The checklist table selector.
   * @param array $content
   *   The current table render array, including form assets.
   * @param bool $completable
   *   Whether all currently required work permits completion.
   */
  public function __construct(string $selector, array $content, protected bool $completable) {
    parent::__construct($selector, $content);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $command = parent::render();
    $command['command'] = 'checklistReconcileRows';
    $command['completable'] = $this->completable;
    return $command;
  }

}
