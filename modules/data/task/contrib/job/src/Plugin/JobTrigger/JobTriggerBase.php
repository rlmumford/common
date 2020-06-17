<?php

namespace Drupal\task_job\Plugin\JobTrigger;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginBase;
use Drupal\entity_template\TemplateBuilderManager;
use Drupal\task\TaskInterface;
use Drupal\task_job\JobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class JobTriggerBase extends ContextAwarePluginBase implements JobTriggerInterface, ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\entity_template\TemplateBuilderManager
   */
  protected $builderManager;

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
    return $this->configuration['key'];
  }

  public function setJob(JobInterface $job) {
    $this->job = $job;
  }

  public function getJob(): JobInterface {
    return $this->job;
  }

  /**
   * @param \Drupal\task_job\JobInterface $job
   * @param array $parameters
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createTask(): TaskInterface {
    /** @var \Drupal\task_job\Plugin\EntityTemplate\Builder\JobTaskBuilder $builder */
    $builder = $this->builderManager->createInstance(
      'task_job:'.$this->getJob()->id()
    );

    $parameters = $this->getContextValues();
    $parameters['job'] = $this->getJob();
    $parameters['trigger'] = $this->getKey();

    $result = $builder->execute($parameters);
    $task = reset($result->getItems());

    return $task;
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
        'label' => 'Default Template',
        'conditions' => [],
        'components' => [],
      ]
    ];
  }
}
