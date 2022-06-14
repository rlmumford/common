<?php

namespace Drupal\checklist\Event;

use Drupal\checklist\ChecklistInterface;

/**
 * Event to collect configuration contexts.
 */
class ChecklistCollectConfigContextsEvent extends ChecklistCollectRuntimeContextsEvent {

  /**
   * A string keyed list of configuration contexts.
   *
   * @var array
   */
  protected $configContexts = [];

  /**
   * Construct an event.
   *
   * @param \Drupal\checklist\ChecklistInterface $checklist
   *   The checklist.
   * @param array $config_contexts
   *   The config contexts.
   */
  public function __construct(ChecklistInterface $checklist, array $config_contexts = []) {
    parent::__construct($checklist);

    $this->configContexts = $config_contexts;
  }

  /**
   * Check whether a config context exists.
   *
   * @param string $name
   *   The name of the configuration context.
   *
   * @return bool
   *   TRUE if the config context exists, FALSE otherwise.
   */
  public function hasConfigContext(string $name) : bool {
    return !empty($this->configContexts[$name]);
  }

  /**
   * Get a config context.
   *
   * @param string $name
   *   The name of the config context.
   *
   * @return mixed|null
   *   The value of the configuration context.
   */
  public function getConfigContext(string $name) {
    return $this->configContexts[$name] ?? NULL;
  }

}
