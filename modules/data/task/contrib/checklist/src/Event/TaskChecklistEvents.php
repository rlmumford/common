<?php

namespace Drupal\task_checklist\Event;

/**
 * Events dispatched by the task checklist module.
 */
class TaskChecklistEvents {

  /**
   * This event is fired right before the checklist is processed to make sure
   * processing happens in the right environment.
   */
  const DETECT_CHECKLIST_ENVIRONMENT = 'task_checklist.detect_checklist_environment';

}
