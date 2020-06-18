<?php

namespace Drupal\task_job\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\task_job\Form\JobAddTriggerForm;
use Drupal\task_job\JobInterface;
use Drupal\task_job\Plugin\JobTrigger\JobTriggerManager;
use Drupal\task_job\TaskJobTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChooseTriggerController extends ControllerBase {
  use AjaxHelperTrait;

  /**
   * @var \Drupal\task_job\Plugin\JobTrigger\JobTriggerManager
   */
  protected $manager;

  /**
   * @var \Drupal\task_job\TaskJobTempstoreRepository
   */
  protected $tempstoreRepository;

  /**
   * [@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('task_job.tempstore_repository'),
      $container->get('plugin.manager.task_job.trigger'),
      $container->get('form_builder')
    );
  }

  /**
   * ChooseHandlerController constructor.
   *
   * @param \Drupal\checklist\ChecklistItemHandlerManager $manager
   */
  public function __construct(
    TaskJobTempstoreRepository $tempstore_repository,
    JobTriggerManager $manager,
    FormBuilderInterface $form_builder
  ) {
    $this->manager = $manager;
    $this->formBuilder = $form_builder;
    $this->tempstoreRepository = $tempstore_repository;
  }

  public function build(JobInterface $task_job) {
    $definitions = $this->manager->getDefinitions();

    if (FALSE && count($definitions) === 1) {
      return $this->formBuilder()->getForm(
        JobAddTriggerForm::class,
        $task_job,
        key($definitions)
      );
    }
    else {
      $build = [
        'links' => [
          '#theme' => 'links',
          '#links' => [],
        ],
      ];

      $job = $this->tempstoreRepository->get($task_job);
      foreach ($definitions as $name => $definition) {
        if ($job->hasTrigger($name) && empty($definition['is_multiple'])) {
          continue;
        }

        $build['links']['#links'][] = [
          'title' => $definition['label'],
          'url' => Url::fromRoute(
            'task_job.trigger.add',
            [
              'task_job' => $task_job->id(),
              'plugin_id' => $name,
            ]
          ),
          'attributes' => $this->getAjaxAttributes(),
        ];
      }

      return $build;
    }
  }

  /**
   * Get the ajax attributes
   *
   * @return array
   */
  protected function getAjaxAttributes() {
    if ($this->isAjax()) {
      return [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
      ];
    }
    return [];
  }

}
