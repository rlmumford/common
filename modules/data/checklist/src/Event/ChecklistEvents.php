<?php

namespace Drupal\checklist\Event;

/**
 * Events for the checklist system.
 */
final class ChecklistEvents {

  /**
   * Event for collecting contexts at run time.
   */
  const COLLECT_RUNTIME_CONTEXTS = 'checklist.collect_runtime_contexts';

  /**
   * Event for collecting contexts at configure time.
   */
  const COLLECT_CONFIG_CONTEXTS = 'checklist.collect_config_contexts';

}
