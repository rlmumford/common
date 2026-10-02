<?php

namespace Drupal\checklist_flexiform\FormData;

use Drupal\flexiform\FormData\FormDataManager;

/**
 * Defers provider persistence until the checklist's fenced result commit.
 */
class ChecklistFormDataManager extends FormDataManager {

  /**
   * The saves requested by a validated final form submission.
   */
  protected ?array $saveExclude = NULL;

  /**
   * {@inheritdoc}
   */
  public function save(array $exclude = []): void {
    $this->saveExclude = $exclude;
  }

  /**
   * Applies configured saves once, inside the owning item's result commit.
   */
  public function commit(): void {
    if ($this->saveExclude !== NULL) {
      parent::save($this->saveExclude);
      $this->saveExclude = NULL;
    }
  }

}
