<?php

namespace Drupal\task_job\Controller;

use Drupal\checklist\ChecklistItemHandlerManager;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\task_job\Form\JobAddChecklistItemForm;
use Drupal\task_job\JobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for adding new checklist items to a job.
 */
class ChooseHandlerController extends ControllerBase {
  use AjaxHelperTrait;

  /**
   * The handler plugin manager.
   *
   * @var \Drupal\checklist\ChecklistItemHandlerManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.checklist_item_handler'),
      $container->get('form_builder')
    );
  }

  /**
   * ChooseHandlerController constructor.
   *
   * @param \Drupal\checklist\ChecklistItemHandlerManager $manager
   *   The checklist item handler manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(
    ChecklistItemHandlerManager $manager,
    FormBuilderInterface $form_builder
  ) {
    $this->manager = $manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * Build the list of checklist item handlers to select from.
   *
   * @param \Drupal\task_job\JobInterface $task_job
   *   The job.
   *
   * @return array
   *   A build array for the page.
   */
  public function build(JobInterface $task_job) {
    $definitions = $this->manager->getDefinitions();

    if (count($definitions) === 1) {
      return $this->formBuilder()->getForm(
        JobAddChecklistItemForm::class,
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

      foreach ($definitions as $name => $definition) {
        $build['links']['#links'][] = [
          'title' => $definition['label'],
          'url' => Url::fromRoute(
            'task_job.checklist_item.add',
            [
              'task_job' => $task_job->id(),
              'handler' => $name,
            ]
          ),
          'attributes' => $this->getAjaxAttributes(),
        ];
      }

      return $build;
    }
  }

  /**
   * Get the ajax attributes.
   *
   * @return array
   *   The ajax attributes for the buttons.
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
