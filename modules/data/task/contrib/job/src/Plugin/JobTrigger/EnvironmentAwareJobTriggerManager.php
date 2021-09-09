<?php


namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\exec_environment\EnvironmentStackInterface;
use Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface;
use Drupal\task_job\JobInterface;

/**
 * The job trigger manager used when the exec_environment module is enabled.
 *
 * @package Drupal\task_job\Plugin\JobTrigger
 */
class EnvironmentAwareJobTriggerManager extends JobTriggerManager {

  /**
   * The environment stack.
   *
   * @var \Drupal\exec_environment\EnvironmentStackInterface
   */
  protected $environmentStack;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Set the environment stack.
   *
   * @param \Drupal\exec_environment\EnvironmentStackInterface $environment_stack
   *   The environment stack.
   *
   * @return $this
   */
  public function setEnvironmentStack(EnvironmentStackInterface $environment_stack) : EnvironmentAwareJobTriggerManager {
    $this->environmentStack = $environment_stack;
    return $this;
  }

  /**
   * Set the config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @return $this
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory) : EnvironmentAwareJobTriggerManager {
    $this->configFactory = $config_factory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTriggerIndex(JobInterface $job) {
    $config = $this->configFactory->getEditable('task_job.task_job.' . $job->id());

    $this->database->delete('task_job_trigger_index')
      ->condition('job', $job->id())
      ->condition('collection', $config->getStorage()->getCollectionName())
      ->execute();

    $insert = $this->database->insert('task_job_trigger_index')
      ->fields(['job', 'trigger', 'trigger_base', 'trigger_key', 'collection']);
    if ($triggers = $job->getTriggersConfiguration()) {
      foreach ($triggers as $key => $trigger_config) {
        $trigger_def = $this->getDefinition($trigger_config['id']);
        $insert->values(
          [
            'job' => $job->id(),
            'trigger' => $trigger_config['id'],
            'trigger_base' => $trigger_def['id'],
            'trigger_key' => $key,
            'collection' => $config->getStorage()->getCollectionName(),
          ]
        );
      }
    }
    else {
      $insert->values([
        'job' => $job->id(),
        'trigger' => 'null',
        'trigger_base' => 'null',
        'trigger_key' => 'null',
        'collection' => $config->getStorage()->getCollectionName(),
      ]);
    }

    $insert->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getTriggers(string $plugin_id = NULL, string $base_plugin_id = NULL): array {
    $environment = $this->environmentStack->getActiveEnvironment();

    /** @var \Drupal\task_job\JobInterface[] $jobs */
    $jobs = [];
    $triggers = [];

    $collection_names = array_filter(array_map(function (ConfigFactoryCollectionComponentInterface $component) {
      $name = $component->getConfigCollectionName();
      return $name ? "environment:{$name}" : NULL;
    }, $environment->getComponents(ConfigFactoryCollectionComponentInterface::class)));
    $collection_names[] = '';

    /** @var \Drupal\exec_environment\Plugin\ExecEnvironment\Component\ConfigFactoryCollectionComponentInterface $component */
    $handled_jobs = [];
    foreach ($collection_names as $collection_name) {
      $query = $this->database->select('task_job_trigger_index', 'i')
        ->fields('i', ['job', 'trigger_key', 'collection'])
        ->condition('collection', $collection_name);

      // Exclude jobs that were loaded from a previous iteration.
      if (!empty($handled_jobs)) {
        $query->condition('job', $handled_jobs, 'NOT IN');
      }

      if (!empty($plugin_id)) {
        $query->condition('trigger', $plugin_id);
      }
      else if (!empty($base_plugin_id)) {
        $query->condition('trigger_base', $base_plugin_id);
      }

      $result = $query->execute();
      foreach ($result as $row) {
        if (empty($jobs[$row->job])) {
          $jobs[$row->job] = $this->jobStorage->load($row->job);
        }

        $triggers[] = $jobs[$row->job]->getTrigger($row->trigger_key);
      }

      // Any job that existed in this collection shouldn't be included in future
      // collections.
      $handled_jobs += $this->database->select('task_job_trigger_index', 'i')
        ->fields('i', ['job'])
        ->condition('collection', $collection_name)
        ->execute()->fetchCol();
    }

    return $triggers;
  }

}
