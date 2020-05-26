<?php

namespace Drupal\task_job\Form;

use Drupal\checklist\ChecklistItemHandlerManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Url;
use Drupal\task_job\JobInterface;
use Drupal\task_job\TaskJobTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JobAddChecklistItemForm extends FormBase {
  use AjaxFormHelperTrait;

  /**
   * @var \Drupal\task_job\TaskJobTempstoreRepository
   */
  protected $tempstoreRepository;

  /**
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('task_job.tempstore_repository'),
      $container->get('plugin_form.factory'),
      $container->get('plugin.manager.checklist_item_handler')
    );
  }

  /**
   * JobEditForm constructor.
   *
   * @param \Drupal\task_job\TaskJobTempstoreRepository $tempstore_repository
   */
  public function __construct(
    TaskJobTempstoreRepository $tempstore_repository,
    PluginFormFactoryInterface $plugin_form_factory,
    ChecklistItemHandlerManager $manager
  ) {
    $this->tempstoreRepository = $tempstore_repository;
    $this->pluginFormFactory = $plugin_form_factory;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_add_checklist_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    JobInterface $task_job = NULL,
    $handler = NULL,
    $handler_config = []
  ) {
    // Get the Job from tempstore if available.
    $job = $task_job;
    if ($this->tempstoreRepository->has($job)) {
      $job = $this->tempstoreRepository->get($job);
    }
    $form_state->set('job', $job);

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#size' => 10,
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
    ];

    $form['handler'] = [
      '#type' => 'value',
      '#value' => $handler,
    ];

    /** @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface $plugin */
    $plugin = $this->manager->createInstance($handler, $handler_config);
    if ($plugin->hasFormClass('configure')) {
      $plugin_form = $this->pluginFormFactory->createInstance($plugin, 'configure');

      $form['handler_configuration'] = [
        '#type' => 'container',
        '#parents' => ['handler_configuration'],
        '#tree' => TRUE,
      ];
      $subform_state = SubformState::createForSubform(
        $form['handler_configuration'],
        $form,
        $form_state
      );
      $form['handler_configuration'] = $plugin_form->buildConfigurationForm(
        $form['handler_configuration'],
        $subform_state
      );
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
      // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
      //   between the various Ajax requests. A bug in
      //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
      //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
      //   ID via Html::getUniqueId(), but here we bypass that in order to work
      //   around the data-drupal-selector bug. This is okay so long as we
      //   assume that this form only ever occurs once on a page. Remove this
      //   workaround in https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $handler = $form_state->getValue('handler');

    /** @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface $plugin */
    $plugin = $this->manager->createInstance($handler);
    if ($plugin->hasFormClass('configure')) {
      $plugin_form = $this->pluginFormFactory->createInstance(
        $plugin,
        'configure'
      );
      $subform_state = SubformState::createForSubform(
        $form['handler_configuration'],
        $form,
        $form_state
      );

      $plugin_form->validateConfigurationForm(
        $form['handler_configuration'],
        $subform_state
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $handler = $form_state->getValue('handler');

    /** @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface $plugin */
    $plugin = $this->manager->createInstance($handler);
    if ($plugin->hasFormClass('configure')) {
      $plugin_form = $this->pluginFormFactory->createInstance(
        $plugin,
        'configure'
      );
      $subform_state = SubformState::createForSubform(
        $form['handler_configuration'],
        $form,
        $form_state
      );

      $plugin_form->submitConfigurationForm(
        $form['handler_configuration'],
        $subform_state
      );
    }

    $job = $form_state->get('job');
    $checklist_items = $job->get('default_checklist');
    $checklist_items[$form_state->getValue('name')] = [
      'name' => $form_state->getValue('name'),
      'label' => $form_state->getValue('label'),
      'handler' => $handler,
      'handler_configuration' => $plugin->getConfiguration(),
    ];
    $job->set('default_checklist', $checklist_items);

    $this->tempstoreRepository->set($job);
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(
    array $form,
    FormStateInterface $form_state
  ) {
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand(Url::fromRoute(
      'entity.task_job.edit_form',
      [
        'task_job' => $form_state->get('job')->id(),
      ]
    )->toString()));

    return $response;
  }
}
