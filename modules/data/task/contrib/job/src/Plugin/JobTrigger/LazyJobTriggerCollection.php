<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\task_job\JobInterface;

class LazyJobTriggerCollection extends DefaultLazyPluginCollection {

  /**
   * @var \Drupal\task_job\JobInterface
   */
  protected $job;

  /**
   * LazyJobTriggerCollection constructor.
   *
   * @param \Drupal\task_job\JobInterface $job
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   * @param array $configurations
   */
  public function __construct(
    JobInterface $job,
    PluginManagerInterface $manager,
    array $configurations = []
  ) {
    $this->job = $job;

    parent::__construct($manager, $configurations);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    $configuration = isset($this->configurations[$instance_id]) ? $this->configurations[$instance_id] : [];
    if (!isset($configuration[$this->pluginKey])) {
      throw new PluginNotFoundException($instance_id);
    }

    $this->set(
      $instance_id,
      $this->manager
        ->createInstance($configuration[$this->pluginKey], $configuration)
        ->setJob($this->job)
    );
  }

  /**
   * Get a job trigger.
   *
   * @param string $instance_id
   *
   * @return \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

}
