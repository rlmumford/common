<?php

namespace Drupal\task_job\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\task_job\JobInterface;

/**
 * Class Job
 *
 * @ConfigEntityType(
 *   id = "task_job",
 * );
 *
 * @package Drupal\task_job\Entity
 *
 * @todo: Make it possible to load overrides.
 */
class Job extends ConfigEntityBase implements JobInterface {

  /**
   * Get the default checklist items for this job.
   *
   * @return array
   *   An array of checklist item configuration keyed by the name.
   *   Each item should have atleast the following keys:
   *     - label - The label of the checklist item
   *     - handler - The handler plugin used for the checklist item.
   *     - handler_configuration - The configuration to be passed to the plugin.
   */
  public function getChecklistItems(): array {
    // @todo: Implement.
    return [];
  }
}
