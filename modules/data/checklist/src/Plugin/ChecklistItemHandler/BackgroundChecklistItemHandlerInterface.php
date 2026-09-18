<?php

namespace Drupal\checklist\Plugin\ChecklistItemHandler;

/**
 * Requires initial execution in a worker, never in the submitting request.
 *
 * Use for potentially slow blocking calls. A request budget cannot interrupt
 * a handler after it starts; such work must explicitly opt into background use.
 */
interface BackgroundChecklistItemHandlerInterface extends IterativeChecklistItemHandlerInterface {}
