<?php

namespace Drupal\task_job\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\entity_template\BlueprintTempstoreRepository;
use Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider\BlueprintStorageJobTriggerAdaptor;
use Drupal\task_job\TaskJobTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JobAddTriggerForm extends JobPluginFormBase {

  /**
   * @var \Drupal\entity_template\BlueprintTempstoreRepository
   */
  protected $blueprintTempstoreRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('task_job.tempstore_repository'),
      $container->get('plugin_form.factory'),
      $container->get('plugin.manager.task_job.trigger'),
      $container->get('config.factory'),
      $container->get('entity_template.blueprint_tempstore_repository')
    );
  }

  /**
   * JobAddTriggerForm constructor.
   *
   * @param \Drupal\task_job\TaskJobTempstoreRepository $tempstore_repository
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\entity_template\BlueprintTempstoreRepository $blueprint_tempstore_repository
   */
  public function __construct(
    TaskJobTempstoreRepository $tempstore_repository,
    PluginFormFactoryInterface $plugin_form_factory,
    PluginManagerInterface $manager,
    ConfigFactoryInterface $config_factory,
    BlueprintTempstoreRepository $blueprint_tempstore_repository
  ) {
    parent::__construct(
      $tempstore_repository, $plugin_form_factory, $manager, $config_factory
    );

    $this->blueprintTempstoreRepository = $blueprint_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_add_trigger_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\task_job\Plugin\JobTrigger\JobTriggerInterface $plugin */
    $plugin = $form_state->get('plugin');
    $job = $form_state->get('job');
    $triggers = $job->get('triggers');
    $triggers[$plugin->getKey()] = [
      'id' => $plugin->getPluginId(),
      'label' => $plugin->getLabel(),
      'key' => $plugin->getKey(),
    ] + $plugin->getConfiguration();
    $job->set('triggers', $triggers);

    $this->tempstoreRepository->set($job);

    // WE also want to clear the blueprint storage for this key.
    $blueprint_storage = new BlueprintStorageJobTriggerAdaptor(
      $job,
      $plugin,
      \Drupal::service('plugin.manager.entity_template.blueprint_provider')
        ->createInstance('job_trigger')
    );
    $this->blueprintTempstoreRepository->delete($blueprint_storage);
  }
}
