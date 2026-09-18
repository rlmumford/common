<?php

namespace Drupal\checklist_state_test\Plugin\ChecklistItemHandler;

use Drupal\checklist\Plugin\ChecklistItemHandler\BackgroundChecklistItemHandlerInterface;

/**
 * Runs the same single-step result handler only in a background worker.
 *
 * @ChecklistItemHandler(
 *   id = "background_step_test",
 *   label = @Translation("Background step test"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("string", required = TRUE)
 *   }
 * )
 */
class BackgroundStep extends SingleStep implements BackgroundChecklistItemHandlerInterface {}
