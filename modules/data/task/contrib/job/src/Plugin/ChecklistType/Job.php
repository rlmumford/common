<?php

namespace Drupal\task_job\Plugin\ChecklistType;

use Drupal\checklist\ChecklistInterface;
use Drupal\checklist\Plugin\ChecklistType\ChecklistTypeBase;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Class Job
 *
 * @ChecklistType(
 *   id = "job",
 *   label = @Translation("Job Checklist"),
 *   entity_type = "task",
 * )
 *
 * @package Drupal\task_job\Plugin\ChecklistType
 */
class Job extends ChecklistTypeBase {



  /**
   * Get the default items.
   *
   * @return \Drupal\checklist\Entity\ChecklistItemInterface
   */
  public function getDefaultItems() {
    // TODO: Implement getDefaultItems() method.
  }


}
