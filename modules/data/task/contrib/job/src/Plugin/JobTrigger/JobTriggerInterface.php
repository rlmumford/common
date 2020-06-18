<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\task\TaskInterface;
use Drupal\task_job\JobInterface;

interface JobTriggerInterface extends PluginInspectionInterface, ConfigurableInterface, ContextAwarePluginInterface {

  /**
   * Get the task.
   *
   * @return \Drupal\task\TaskInterface
   */
  public function createTask() : TaskInterface;

  /**
   * Get the key.
   *
   * @return string
   */
  public function getKey(): string;

  /**
   * Set the job
   *
   * @param \Drupal\task_job\JobInterface $job
   *
   * @return \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface
   */
  public function setJob(JobInterface $job): JobTriggerInterface;

  public function getLabel();

  public function getDescription();

}
