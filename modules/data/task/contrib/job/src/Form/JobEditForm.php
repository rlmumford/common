<?php

namespace Drupal\task_job\Form;

use Drupal\checklist\ChecklistItemHandlerManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\entity_template\BlueprintEntityStorageAdaptor;
use Drupal\entity_template\BlueprintTempstoreRepository;
use Drupal\entity_template\TemplateBlueprintProviderManager;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider\BlueprintStorageJobTriggerAdaptor;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerManager;
use Drupal\task_job\TaskJobTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JobEditForm extends JobForm {

  /**
   * @var \Drupal\task_job\Entity\Job
   */
  protected $entity;

  /**
   * @var \Drupal\task_job\Plugin\EntityTemplate\BlueprintProvider\BlueprintStorageJobTriggerAdaptor[]
   */
  protected $blueprintStorages;

  /**
   * @var \Drupal\entity_template\TemplateBlueprintProviderManager
   */
  protected $blueprintProviderManager;

  /**
   * @var \Drupal\entity_template\BlueprintTempstoreRepository
   */
  protected $blueprintTempstoreRepository;

  /**
   * @var \Drupal\task_job\TaskJobTempstoreRepository
   */
  protected $tempstoreRepository;

  /**
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * @var \Drupal\checklist\ChecklistItemHandlerManager
   */
  protected $manager;

  /**
   * @var \Drupal\task_job\Plugin\JobTrigger\JobTriggerManager
   */
  protected $jobTriggerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('task_job.tempstore_repository'),
      $container->get('plugin.manager.checklist_item_handler'),
      $container->get('plugin.manager.entity_template.blueprint_provider'),
      $container->get('entity_template.blueprint_tempstore_repository'),
      $container->get('plugin_form.factory'),
      $container->get('plugin.manager.task_job.trigger')
    );
  }

  /**
   * JobEditForm constructor.
   *
   * @param \Drupal\task_job\TaskJobTempstoreRepository $tempstore_repository
   */
  public function __construct(
    TaskJobTempstoreRepository $tempstore_repository,
    ChecklistItemHandlerManager $manager,
    TemplateBlueprintProviderManager $blueprint_provider_manager,
    BlueprintTempstoreRepository $blueprint_tempstore_repository,
    PluginFormFactoryInterface $plugin_form_factory,
    JobTriggerManager $job_trigger_manager
  ) {
    $this->tempstoreRepository = $tempstore_repository;
    $this->blueprintTempstoreRepository = $blueprint_tempstore_repository;
    $this->manager = $manager;
    $this->blueprintProviderManager = $blueprint_provider_manager;
    $this->pluginFormFactory = $plugin_form_factory;
    $this->jobTriggerManager = $job_trigger_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(EntityInterface $entity) {
    if (!($entity instanceof JobInterface)) {
      throw new \InvalidArgumentException('This form can only be used with job entities.');
    }

    if ($this->tempstoreRepository->has($entity)) {
      $entity = $this->tempstoreRepository->get($entity);
    }
    else {
      $this->tempstoreRepository->set($entity);
    }

    return parent::setEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['checklist'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Checklist'),
      '#description' => $this->t('Some help text about checklists'),
      '#open' => TRUE,
    ];

    $ajax_attributes = [
      'attributes' => [
        'class' => [
          'use-ajax',
        ],
        'data-dialog-type' => 'dialog',
      ],
    ];

    $form['checklist']['add'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Checklist Item'),
      '#url' => Url::fromRoute(
        'task_job.checklist_item.choose_handler',
        [
          'task_job' => $this->entity->id(),
        ],
        $ajax_attributes
      ),
      '#attributes' => [
        'class' => ['add-checklist-item-button', 'btn', 'button']
      ],
    ];

    $form['checklist']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Title'),
        $this->t('Summary'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No checklist items are configured'),
    ];
    foreach ($this->entity->getChecklistItems() as $name => $definition) {
      /** @var \Drupal\checklist\Plugin\ChecklistItemHandler\ChecklistItemHandlerInterface $plugin */
      $plugin = $this->manager->createInstance(
        $definition['handler'],
        $definition['handler_configuration']
      );

      $row = [];
      $row['name'] = [
        '#markup' => $name,
      ];
      $row['title'] = [
        '#markup' => $definition['label'],
      ];
      $row['summary'] = $plugin->buildConfigurationSummary();
      $row['operations'] = [
        '#type' => 'dropbutton',
        '#links' => [
          'configure' => [
            'title' => $this->t('configure'),
            'url' => Url::fromRoute(
              'task_job.checklist_item.configure',
              [
                'task_job' => $this->entity->id(),
                'name' => $name,
              ],
              [
                'query' => $this->getDestinationArray(),
              ] + $ajax_attributes
            ),
          ],
          'remove' => [
            'title' => $this->t('remove'),
            'url' => Url::fromRoute(
              'task_job.checklist_item.remove',
              [
                'task_job' => $this->entity->id(),
                'name' => $name,
              ],
              [
                'query' => $this->getDestinationArray(),
              ] + $ajax_attributes
            )
          ]
        ],
      ];

      $form['checklist']['table'][$name] = $row;
    }

    $form['triggers'] = [
      '#type' => 'details',
      '#title' => $this->t('Triggers'),
      '#description' => $this->t('What triggers tasks of this job?'),
      '#tree' => TRUE,
    ];
    $form['triggers']['__add'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Trigger'),
      '#url' => Url::fromRoute(
        'task_job.trigger.choose',
        [
          'task_job' => $this->entity->id(),
        ],
        $ajax_attributes
      ),
      '#attributes' => [
        'class' => ['add-trigger-button', 'btn', 'button']
      ],
    ];

    foreach ($this->entity->getTriggerCollection() as $key => $trigger) {
      $element = [
        '#type' => 'details',
        '#title' => $trigger->getLabel(),
        '#description' => $trigger->getDescription(),
      ];
      $element['template'] = [
        '#type' => 'container',
        '#title' => $this->t('Template'),
        '#description' => $this->t('Configure how a task gets created with this job'),
        '#open' => TRUE,
        '#parents' => ['triggers', $key, 'template'],
      ];

      $storage = new BlueprintStorageJobTriggerAdaptor(
        $this->entity,
        $trigger,
        $this->blueprintProviderManager->createInstance('job_trigger')
      );
      $storage = $this->blueprintTempstoreRepository->get($storage);
      $this->blueprintTempstoreRepository->set($storage);
      $this->blueprintStorages[$key] = $storage;
      $template = $this->blueprintStorages[$key]->getTemplate('default');

      if (($template instanceof PluginWithFormsInterface) && $template->hasFormClass("configure")) {
        $plugin_form = $this->pluginFormFactory->createInstance(
          $template,
          "configure"
        );

        $element['template'] = $plugin_form->buildConfigurationForm(
          $element['template'],
          SubformState::createForSubform(
            $element['template'],
            $form,
            $form_state
          )
        );

        // Hide the label and description fields as we don't need them.
        $element['template']['label']['#access'] = FALSE;
        $element['template']['description']['#access'] = FALSE;
        $element['template']['conditions']['#access'] = FALSE;
      }

      $form['triggers'][$key] = $element;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    foreach (Element::children($form['triggers']) as $key) {
      if ($key === '__add') {
        continue;
      }

      $storage = $this->blueprintStorages[$key];
      $template = $storage->getTemplate('default');

      if (
        ($template instanceof PluginWithFormsInterface) &&
        $template->hasFormClass("configure")
      ) {
        $plugin_form = $this->pluginFormFactory->createInstance(
          $template,
          "configure"
        );

        $plugin_form->submitConfigurationForm(
          $form['triggers'][$key]['template'],
          SubformState::createForSubform(
            $form['triggers'][$key]['template'],
            $form,
            $form_state
          )
        );
      }
    }

    $triggers_config = [];
    foreach ($this->blueprintStorages as $key => $storage) {
      $triggers_config[$key] = [
        'id' => $storage->getTrigger()->getPluginId(),
        'key' => $storage->getTrigger()->getKey(),
        'template' => $storage->getTemplate('default')->getConfiguration(),
      ] + $storage->getTrigger()->getConfiguration();
    }

    $this->entity->set('triggers', $triggers_config);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);

    foreach ($this->blueprintStorages as $key => $storage) {
      $this->blueprintTempstoreRepository->delete($storage);
    }
    $this->tempstoreRepository->delete($this->entity);
    return $return;
  }
}
