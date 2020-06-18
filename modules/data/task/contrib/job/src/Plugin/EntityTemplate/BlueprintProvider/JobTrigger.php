<?php

namespace Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_template\BlueprintStorageInterface;
use Drupal\entity_template\Plugin\EntityTemplate\BlueprintProvider\BlueprintProviderInterface;
use Drupal\entity_template\Plugin\EntityTemplate\Builder\BuilderInterface;
use Drupal\task_job\Plugin\EntityTemplate\Builder\JobTaskBuilder;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class JobTrigger
 *
 * @EntityTemplateBlueprintProvider(
 *   id = "job_trigger",
 *   label = @Translation("Job Trigger")
 * )
 *
 * @package Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider
 */
class JobTrigger extends PluginBase implements BlueprintProviderInterface, ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\task_job\Plugin\JobTrigger\JobTriggerManager
   */
  protected $jobTriggerManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.task_job.trigger'),
      $container->get('entity_type.manager')
    );
  }

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    JobTriggerManager $job_trigger_manager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->jobTriggerManager = $job_trigger_manager;
    $this->entityTypeManager = $entity_type_manager;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Get all the available blueprints for a given builder
   *
   * @param \Drupal\entity_template\Plugin\EntityTemplate\Builder\BuilderInterface $builder
   *
   * @return \Drupal\entity_template\BlueprintInterface[]
   */
  public function getAllBlueprints(BuilderInterface $builder) {
    if (!$builder instanceof JobTaskBuilder) {
      return [];
    }

    $job = $builder->getJob();
    $bps = [];
    foreach ($job->getTriggersConfiguration() as $trigger => $configuration) {
      $bps[$trigger] = new BlueprintJobTriggerAdaptor(
        $job,
        $this->jobTriggerManager->createInstance($configuration['id'], $configuration)
      );
    }

    return $bps;
  }

  /**
   * Get the available blueprints for a given builder, limiting based on
   *
   * @param \Drupal\entity_template\Plugin\EntityTemplate\Builder\BuilderInterface $builder
   * @param array $parameters
   * @param \Drupal\Core\Session\AccountInterface|NULL $account
   *
   * @return \Drupal\entity_template\BlueprintInterface[]
   */
  public function getAvailableBlueprints(
    BuilderInterface $builder,
    $parameters = [],
    AccountInterface $account = NULL
  ) {
    if (!($builder instanceof JobTaskBuilder) || !isset($parameters['job_trigger'])) {
      return $this->getAllBlueprints($builder);
    }

    $job = $builder->getJob();
    $triggers = $job->getTriggersConfiguration();
    $trigger = $parameters['job_trigger'] instanceof ContextInterface ? $parameters['job_trigger']->getContextValue() : $parameters['job_trigger'];

    if (!isset($triggers[$trigger])) {
      return [];
    }
    else {
      return [
        $trigger => new BlueprintJobTriggerAdaptor(
          $job,
          $this->jobTriggerManager->createInstance(
            $triggers[$trigger]['id'],
            $triggers[$trigger]
          )
        ),
      ];
    }
  }

  /**
   * Get a blueprint storage.
   *
   * @param $id
   *
   * @return \Drupal\entity_template\BlueprintStorageInterface
   */
  public function getBlueprintStorage($id) {
    list($job_id, $trigger) = explode('.', $id, 2);

    /** @var \Drupal\task_job\JobInterface $job */
    $job = $this->entityTypeManager->getStorage('task_job')->load($job_id);
    $triggers = $job->getTriggersConfiguration();

    if (!isset($triggers[$trigger])) {
      return NULL;
    }

    return new BlueprintStorageJobTriggerAdaptor(
      $job,
      $this->jobTriggerManager->createInstance(
        $triggers[$trigger]['id'],
        $triggers[$trigger]
      ),
      $this
    );
  }

  /**
   * Get the key of a given blueprint to identify it in the provider.
   *
   * @param \Drupal\entity_template\BlueprintStorageInterface $blueprintStorage
   *
   * @return string
   */
  public function getBlueprintKey(BlueprintStorageInterface $blueprint_storage) {
    if (!($blueprint_storage instanceof BlueprintStorageJobTriggerAdaptor)) {
      throw new \Exception('Invalid blueprint storage provided.');
    }

    $job = $blueprint_storage->getJob();
    $trigger = $blueprint_storage->getTrigger();

    return "{$job->id()}.{$trigger->getKey()}";
  }

  /**
   * @param \Drupal\entity_template\BlueprintStorageInterface $blueprint_storage
   *
   * @return \Drupal\Core\Url
   */
  public function getBlueprintEditUrl(BlueprintStorageInterface $blueprint_storage) {
    if (!($blueprint_storage instanceof BlueprintStorageJobTriggerAdaptor)) {
      throw new \Exception('Invalid blueprint storage provided.');
    }

    return new Url(
      'entity.task_job.edit_form',
      [
        'task_job' => $blueprint_storage->getJob()->id(),
      ]
    );
  }

  /**
   * Get the edit template url for the blueprint.
   *
   * @param \Drupal\entity_template\BlueprintStorageInterface $blueprint_storage
   * @param string $key
   *
   * @return \Drupal\Core\Url
   *
   * @todo: Remove in favour of TemplateUI classes
   */
  public function getBlueprintEditTemplateUrl(BlueprintStorageInterface $blueprint_storage, string $key): Url {#
    if (!($blueprint_storage instanceof BlueprintStorageJobTriggerAdaptor)) {
      throw new \Exception('Invalid blueprint storage provided.');
    }

    return new Url(
      'entity.task_job.edit_form',
      [
        'task_job' => $blueprint_storage->getJob()->id(),
      ]
    );
  }
}
