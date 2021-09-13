<?php

namespace Drupal\task_job\Event;

/**
 * Events dispatched by TaskJob module.
 *
 * @package Drupal\task_job\Event
 */
final class TaskJobEvents {

  /**
   * Select job detect environment.
   *
   * This event is fired on the select job page to make sure the right jobs
   * are loaded based on the environment.
   */
  const SELECT_JOB_DETECT_ENVIRONMENT = 'task_job.select_job_detect_environment';

}
