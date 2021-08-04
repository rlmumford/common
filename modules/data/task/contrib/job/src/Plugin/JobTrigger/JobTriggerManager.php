<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextAwarePluginManagerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\task_job\Annotation\JobTrigger;
use Drupal\task_job\JobInterface;

/**
 * Manage job triggers.
 */
class JobTriggerManager extends DefaultPluginManager implements JobTriggerManagerInterface {
  use ContextAwarePluginManagerTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The job storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $jobStorage;

  /**
   * JobTriggerManager constructor.
   *
   * @param \Traversable $namespaces
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct(
      'Plugin/JobTrigger',
      $namespaces,
      $module_handler,
      JobTriggerInterface::class,
      JobTrigger::class
    );

    $this->alterInfo('job_trigger_info');
    $this->setCacheBackend($cache_backend, 'job_trigger_info');

    $this->database = $database;
    $this->jobStorage = $entity_type_manager->getStorage('task_job');
  }

  /**
   * {@inheritdoc}
   */
  public function updateTriggerIndex(JobInterface $job) {
    $this->database->delete('task_job_trigger_index')
      ->condition('job', $job->id())
      ->execute();

    $insert = $this->database->insert('task_job_trigger_index')
      ->fields(['job', 'trigger', 'trigger_key']);
    foreach ($job->getTriggersConfiguration() as $key => $config) {
      $insert->values([
        'job' => $job->id(),
        'trigger' => $config['id'],
        'trigger_key' => $key,
      ]);
    }
    $insert->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getTriggers(string $plugin_id) : array {
    $result = $this->database->select('task_job_trigger_index', 'i')
      ->condition('trigger', $plugin_id)
      ->fields('i', ['job', 'trigger_key'])
      ->execute();

    /** @var \Drupal\task_job\JobInterface[] $jobs */
    $jobs = [];
    $triggers = [];
    foreach ($result as $row) {
      if (empty($jobs[$row->job])) {
        $jobs[$row->job] = $this->jobStorage->load($row->job);
      }

      $triggers[] = $jobs[$row->job]->getTrigger($row->trigger_key);
    }

    return $triggers;
  }
}
