<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

use Drupal\checklist\ChecklistActionState;

/**
 * Exposes safe progress to viewers without executing or advancing an action.
 */
interface ActionStateChecklistItemHandlerInterface extends ChecklistItemHandlerInterface {

  /**
   * Projects current stored state for the current authorized viewer.
   *
   * Called after view access and runtime context preparation. Implementations
   * must not save, acquire locks, run actions, advance continuations or refresh
   * external providers. Read stored state/run records and redact values
   * not appropriate for this viewer. Do not copy raw prompts or errors into the
   * message. This is display information, never authorization to execute.
   *
   * @return \Drupal\checklist\ChecklistActionState|null
   *   Safe progress, or NULL when no plugin-specific progress is available.
   */
  public function getActionState(): ?ChecklistActionState;

}
