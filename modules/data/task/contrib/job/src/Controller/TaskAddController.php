<?php

namespace Drupal\task_job\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\task_job\JobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TaskAddController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * TaskAddController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * Shows a list of Jobs to create.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function selectJob() {
    $build = [
      '#theme' => 'item_list',
      '#items' => [],
    ];

    $job_storage = $this->entityTypeManager->getStorage('task_job');
    foreach ($job_storage->loadMultiple() as $id => $job) {
      $build['#items'][] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('task_job.task.add_form', ['task_job' => $id]),
        '#title' => $job->label(),
        '#attributes' => [
          'class' => ['use-ajax'],
        ]
      ];
    }

    return $build;
  }

  /**
   * Show a form to create a task.
   *
   * @param \Drupal\task_job\JobInterface $task_job
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createTask(JobInterface $task_job) {
    $task = $this->entityTypeManager->getStorage('task')
      ->create(['job' => $task_job]);
    return $this->entityFormBuilder->getForm($task, 'add');
  }

}
