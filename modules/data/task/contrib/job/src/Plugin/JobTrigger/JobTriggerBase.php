<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_template\Exception\NoAvailableBlueprintException;
use Drupal\entity_template\TemplateBuilderManager;
use Drupal\task\TaskInterface;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider\BlueprintJobTriggerAdaptor;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class JobTriggerBase extends ContextAwarePluginBase implements JobTriggerInterface, ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\entity_template\TemplateBuilderManager
   */
  protected $builderManager;

  /**
   * @var \Drupal\task_job\JobInterface
   */
  protected $job;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\task_job\Plugin\JobTrigger\JobTriggerBase
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_template.builder')
    );
  }

  /**
   * JobTriggerBase constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\entity_template\TemplateBuilderManager $builder_manager
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TemplateBuilderManager $builder_manager
  ) {
    $this->builderManager = $builder_manager;

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): string {
    return isset($this->configuration['key']) ?
      $this->configuration['key'] :
      $this->getDefaultKey();
  }

  /**
   * Get the default key for this trigger.
   *
   * @return string
   */
  abstract protected function getDefaultKey(): string;

  /**
   * {@inheritdoc}
   */
  public function setJob(JobInterface $job): JobTriggerInterface {
    $this->job = $job;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getJob(): JobInterface {
    return $this->job;
  }

  /**
   * {@inheritdoc}
   */
  public function createTask(): ?TaskInterface {
    /** @var \Drupal\task_job\Plugin\EntityTemplate\Builder\JobTaskBuilder $builder */
    $builder = $this->builderManager->createInstance(
      'task_job:'.$this->getJob()->id()
    );

    $parameters = $this->getContextValues();
    $parameters['job'] = $this->getJob();
    $parameters['trigger'] = $this->getKey();

    try {
      $result = $builder->execute($parameters);

      /** @var \Drupal\task\Entity\Task[] $tasks */
      $tasks = $result->getItems();
      return !empty($tasks) ? reset($tasks) : NULL;
    }
    catch (NoAvailableBlueprintException $e) {
      return NULL;
    }
  }

  /**
   * Gets this plugin's configuration.
   *
   * @return array
   *   An array of this plugin's configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * Gets default configuration for this plugin.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  public function defaultConfiguration() {
    return [
      'template' => [
        'id' => 'default',
        'uuid' => 'default',
        'label' =>  new TranslatableMarkup('Template'),
        'conditions' => [],
        'components' => (new BlueprintJobTriggerAdaptor($this->getJob(), $this))
          ->getDefaultTemplateComponents(),
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function access(CacheableMetadata $cache_metadata = NULL) {
    $storage = new BlueprintJobTriggerAdaptor($this->getJob(), $this);
    $template = $storage->getTemplate('default');

    return $template->applies($cache_metadata);
  }
}
