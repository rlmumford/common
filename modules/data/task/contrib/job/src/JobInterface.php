<?php

namespace Drupal\task_job;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_template\Entity\BlueprintEntityInterface;

interface JobInterface extends EntityInterface {

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
  public function getChecklistItems() : array;

  /**
   * Get the triggers associated with this job.
   *
   * @return array
   */
  public function getTriggers() : array;

}
