<?php

namespace Drupal\task_ui\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\task\TaskInterface;
use Drupal\task_ui\WorkQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Presents shared tasks without an alternate checklist or completion workflow.
 */
final class WorkController extends ControllerBase {

  /**
   * Constructs the workspace controller.
   */
  public function __construct(private readonly WorkQueue $queue) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('task_ui.queue'));
  }

  /**
   * Lists one bounded, access-checked queue.
   */
  public function listing(Request $request): array {
    return $this->queue->build($request->query->getString('queue', 'team'), mb_substr($request->query->getString('q'), 0, 255));
  }

  /**
   * Shows current readiness, context and the existing interactive checklist.
   */
  public function task(TaskInterface $task): array {
    if (!$task->access('view')) {
      throw new AccessDeniedHttpException();
    }
    $rows = [
      [$this->t('Status'), $this->queue->state($task)],
      [$this->t('Assigned to'), $this->queue->assignee($task)],
      [$this->t('Start'), $this->queue->date($task, 'start')],
      [$this->t('Due'), $this->queue->date($task, 'due', TRUE)],
      [$this->t('External deadline'), $this->queue->date($task, 'deadline', TRUE)],
    ];
    foreach ($this->queue->contextFields() as $field => $label) {
      if ($task->hasField($field) && $task->get($field)->access('view')) {
        $rows[] = [$label, $this->queue->reference($task->get($field)->entity)];
      }
    }
    $build = [
      '#cache' => ['max-age' => 0],
      '#attached' => ['library' => ['task_ui/workspace']],
      'back' => ['#type' => 'link', '#title' => $this->t('Task queues'), '#url' => Url::fromRoute('task_ui.tasks')],
      'summary' => ['#type' => 'table', '#rows' => $rows, '#attributes' => ['class' => ['task-work-summary']]],
      'actions' => ['#type' => 'container', '#attributes' => ['class' => ['task-work-actions']]] + $this->queue->actions($task),
    ];
    foreach (['description' => 'text_default', 'checklist' => 'checklist_interactive'] as $field => $formatter) {
      if ($task->hasField($field) && $task->get($field)->access('view')) {
        $build[$field] = $task->get($field)->view(['label' => 'hidden', 'type' => $formatter]);
      }
    }
    return $build;
  }

}
