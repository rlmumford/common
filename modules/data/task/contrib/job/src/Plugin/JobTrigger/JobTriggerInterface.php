<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\task\TaskInterface;
use Drupal\task_job\JobInterface;

interface JobTriggerInterface extends PluginInspectionInterface, ConfigurableInterface, ContextAwarePluginInterface {

  /**
   * Get the task.
   *
   * @return \Drupal\task\TaskInterface|null
   */
  public function createTask() : ?TaskInterface;

  /**
   * Does this trigger have access to run.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata|null $bubbleable_metadata
   *   The cache metadata.
   *
   * @return bool
   *   TRUE if the trigger can fire, false otherwise.
   */
  public function access(CacheableMetadata $bubbleable_metadata = NULL);

  /**
   * Get the key.
   *
   * @return string
   */
  public function getKey(): string;

  /**
   * Set the job.
   *
   * @param \Drupal\task_job\JobInterface $job
   *   The job.
   *
   * @return \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface
   */
  public function setJob(JobInterface $job): JobTriggerInterface;

  /**
   * Get the job.
   *
   * @return \Drupal\task_job\JobInterface
   *   The job.
   */
  public function getJob(): JobInterface;

  public function getLabel();

  public function getDescription();

}
