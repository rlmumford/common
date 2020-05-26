<?php

namespace Drupal\task_job\Form;

use Drupal\checklist\ChecklistItemHandlerManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\task_job\JobInterface;
use Drupal\task_job\TaskJobTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JobEditForm extends JobForm {

  /**
   * @var \Drupal\task_job\Entity\Job
   */
  protected $entity;

  /**
   * @var \Drupal\task_job\TaskJobTempstoreRepository
   */
  protected $tempstoreRepository;

  /**
   * @var \Drupal\checklist\ChecklistItemHandlerManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('task_job.tempstore_repository'),
      $container->get('plugin.manager.checklist_item_handler')
    );
  }

  /**
   * JobEditForm constructor.
   *
   * @param \Drupal\task_job\TaskJobTempstoreRepository $tempstore_repository
   */
  public function __construct(TaskJobTempstoreRepository $tempstore_repository, ChecklistItemHandlerManager $manager) {
    $this->tempstoreRepository = $tempstore_repository;
    $this->manager = $manager;
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);
    $this->tempstoreRepository->delete($this->entity);
    return $return;
  }
}
