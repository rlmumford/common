<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * JobTriggerManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
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
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get the job storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The job storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function jobStorage() : EntityStorageInterface {
    if (!$this->jobStorage) {
      $this->jobStorage = $this->entityTypeManager->getStorage('task_job');
    }
    return $this->jobStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTriggerIndex(JobInterface $job) {
    $this->database->delete('task_job_trigger_index')
      ->condition('job', $job->id())
      ->execute();

    if ($job->status()) {
      $insert = $this->database->insert('task_job_trigger_index')
        ->fields(['job', 'trigger', 'trigger_base', 'trigger_key']);
      foreach ($job->getTriggersConfiguration() as $key => $config) {
        $trigger_def = $this->getDefinition($config['id']);
        $insert->values(
          [
            'job' => $job->id(),
            'trigger' => $config['id'],
            'trigger_base' => $trigger_def['id'],
            'trigger_key' => $key,
          ]
        );
      }
      $insert->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTriggers(string $plugin_id = NULL, string $base_plugin_id = NULL) : array {
    $query = $this->database->select('task_job_trigger_index', 'i')
      ->fields('i', ['job', 'trigger_key']);

    if (!empty($plugin_id)) {
      $query->condition('trigger', $plugin_id);
    }
    elseif (!empty($base_plugin_id)) {
      $query->condition('trigger_base', $base_plugin_id);
    }

    $result = $query->execute();

    /** @var \Drupal\task_job\JobInterface[] $jobs */
    $jobs = [];
    $triggers = [];
    foreach ($result as $row) {
      if (empty($jobs[$row->job])) {
        $jobs[$row->job] = $this->jobStorage()->load($row->job);
      }

      $triggers[] = $jobs[$row->job]->getTrigger($row->trigger_key);
    }

    return $triggers;
  }

  /**
   * {@inheritdoc}
   */
  public function getInUseTriggerIds(string $base_plugin_id = NULL): array {
    $query = $this->database->select('task_job_trigger_index', 'i');
    $query->addField('i', 'trigger', 'trigger');
    $query->distinct();
    if ($base_plugin_id) {
      $query->condition('trigger_base', $base_plugin_id);
    }
    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function handleTrigger(string $plugin_id, array $context_values, bool $save = TRUE) : array {
    $tasks = [];
    foreach ($this->getTriggers($plugin_id) as $trigger) {
      foreach ($trigger->getContextDefinitions() as $name => $definition) {
        if (isset($context_values[$name])) {
          $trigger->setContextValue($name, $context_values[$name]);
        }
      }

      if ($trigger->access() && ($task = $trigger->createTask())) {
        $tasks[] = $task;
        if ($save) {
          $task->save();
        }
      }
    }

    return $tasks;
  }

}
