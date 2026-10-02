<?php

namespace Drupal\checklist_flexiform\Plugin\ChecklistItemHandler;

use Drupal\checklist\Execution\ChecklistItemResult;
use Drupal\checklist\Plugin\ChecklistItemHandler\IterativeChecklistItemHandlerInterface;
use Drupal\flexiform\Session\FormSession;

/**
 * Supplies a caller-owned Flexiform session to the checklist operation host.
 */
interface FormItemHandlerInterface extends IterativeChecklistItemHandlerInterface {

  /**
   * Returns optional initial choices, or NULL when the form needs no choice.
   */
  public function editorChoices(): ?array;

  /**
   * Selects one advertised initial choice before starting preparation.
   */
  public function selectEditorChoice(string $key): void;

  /**
   * Returns the private session and handler metadata, or NULL before starting.
   */
  public function getEditorSession(): ?array;

  /**
   * Retains working input or produces a fenced completion result.
   */
  public function editorResult(FormSession $session, array $metadata, bool $complete = FALSE): ChecklistItemResult;

}
