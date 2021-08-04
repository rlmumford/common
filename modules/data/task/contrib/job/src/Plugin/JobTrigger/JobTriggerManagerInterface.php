<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\task_job\JobInterface;

/**
 * Interface for Job Trigger Manager services.
 */
interface JobTriggerManagerInterface {

  /**
   * Update the job trigger index.
   *
   * @param \Drupal\task_job\JobInterface $job
   *   The job to update the index for.
   */
  public function updateTriggerIndex(JobInterface $job);

  /**
   * Get the trigger plugin instances for this plugin id.
   *
   * @param string $plugin_id
   *   The trigger plugin id.
   *
   * @return \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface[]
   *   A list of triggers.
   */
  public function getTriggers(string $plugin_id) : array;

}
