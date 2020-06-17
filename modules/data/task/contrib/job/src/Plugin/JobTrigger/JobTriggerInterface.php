<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\task_job\JobInterface;

interface JobTriggerInterface extends PluginInspectionInterface, ConfigurableInterface, ContextAwarePluginInterface {

  /**
   * Get the key.
   *
   * @return string
   */
  public function getKey(): string;

  /**
   * @param \Drupal\task_job\Plugin\JobTrigger\JobInterface $job
   *
   * @return mixed
   */
  public function setJob(JobInterface $job);

  public function getLabel();

  public function getDescription();

}
