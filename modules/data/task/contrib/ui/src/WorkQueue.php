<?php

namespace Drupal\task_ui;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\task\TaskInterface;
use Drupal\task\TaskReadiness;

/**
 * Bounded task queues with live shared readiness and safe references.
 */
final class WorkQueue {

  use StringTranslationTrait;

  /**
   * Constructs the work list presenter.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly TaskReadiness $readiness,
    private readonly DateFormatterInterface $dates,
    private readonly TimeInterface $time,
    private readonly ModuleHandlerInterface $modules,
  ) {}

  /**
   * Builds one page; query access scopes both results and the pager count.
   */
  public function build(string $queue = 'team', string $search = ''): array {
    $queues = [
      'mine' => $this->t('My work'),
      'unassigned' => $this->t('Unassigned'),
      'team' => $this->t('Team work'),
      'history' => $this->t('History'),
    ];
    $queue = isset($queues[$queue]) ? $queue : 'team';
    $build = ['#cache' => ['max-age' => 0], '#attached' => ['library' => ['task_ui/workspace']]];
    $build['heading'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $queues[$queue]];
    foreach ($queues as $key => $label) {
      $links[] = Link::fromTextAndUrl($label, Url::fromRoute('task_ui.tasks', [], ['query' => ['queue' => $key]]));
    }
    $this->modules->alter('task_ui_navigation', $links);
    $build['navigation'] = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => ['class' => ['task-work-navigation']],
    ];
    $build['search'] = [
      '#type' => 'container',
      'form' => [
        '#type' => 'html_tag',
        '#tag' => 'form',
        '#attributes' => ['method' => 'get', 'action' => Url::fromRoute('task_ui.tasks')->toString()],
        'queue' => ['#type' => 'hidden', '#name' => 'queue', '#value' => $queue],
        'q' => [
          '#type' => 'search',
          '#title' => $this->t('Task title'),
          '#name' => 'q',
          '#value' => $search,
          '#id' => 'task-work-search',
        ],
        'submit' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Search'),
          '#attributes' => ['type' => 'submit'],
        ],
      ],
    ];
    $storage = $this->entities->getStorage('task');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $query->condition('status', ['resolved', 'closed'], $queue === 'history' ? 'IN' : 'NOT IN');
    if ($queue === 'mine') {
      $query->condition('assignee', $this->account->id());
    }
    elseif ($queue === 'unassigned') {
      $query->notExists('assignee');
    }
    if ($search !== '') {
      $query->condition('title', $search, 'CONTAINS');
    }
    // Stable pages avoid scanning the whole organization to evaluate readiness.
    // Group this page by live state; stored status can lag the scheduler.
    $tasks = $storage->loadMultiple($query->sort('id', 'DESC')->pager(25)->execute());
    $fields = $this->contextFields();
    $this->preloadReferences($tasks, $fields);
    $groups = array_fill_keys(['active', 'pending', 'waiting', 'invalid', 'resolved', 'closed'], []);
    foreach ($tasks as $task) {
      if (!$task->access('view')) {
        continue;
      }
      $state = $this->readiness->evaluate($task)->state;
      $row = [$task->toLink()];
      foreach ($fields as $field => $label) {
        $row[] = $task->hasField($field) && $task->get($field)->access('view') ? $this->reference($task->get($field)->entity) : $this->t('—');
      }
      $groups[$state][] = array_merge($row, [
        $this->assignee($task),
        $this->date($task, 'due', TRUE),
        $this->date($task, 'deadline', TRUE),
        ['data' => $this->actions($task)],
      ]);
    }
    $build['ordering'] = [
      '#type' => 'container',
      '#plain_text' => $this->t('Newest tasks first, grouped by current readiness within each page.'),
    ];
    foreach ($groups as $state => $rows) {
      if ($rows) {
        $build[$state] = [
          '#type' => 'table',
          '#caption' => $this->stateLabel($state),
          '#header' => array_merge([$this->t('Task')], array_values($fields), [
            $this->t('Assigned to'), $this->t('Due'),
            $this->t('External deadline'), $this->t('Actions'),
          ]),
          '#rows' => $rows,
        ];
      }
    }
    if (!array_filter($groups)) {
      $build['empty'] = ['#plain_text' => $this->t('No tasks in this queue.')];
    }
    $build['pager'] = ['#type' => 'pager'];
    return $build;
  }

  /**
   * Batch-loads reference labels for this bounded page.
   */
  private function preloadReferences(array $tasks, array $fields): void {
    foreach (array_keys($fields + ['assignee' => 'Assigned to']) as $field) {
      $type = NULL;
      $ids = [];
      foreach ($tasks as $task) {
        if ($task->hasField($field) && ($id = $task->get($field)->target_id)) {
          $type = $task->get($field)->getFieldDefinition()->getSetting('target_type');
          $ids[$id] = $id;
        }
      }
      if ($ids) {
        $this->entities->getStorage($type)->loadMultiple($ids);
      }
    }
  }

  /**
   * Shows only references independently visible to this viewer.
   */
  public function reference(?EntityInterface $entity): mixed {
    return $entity && $entity->access('view') ? $entity->toLink() : $this->t('—');
  }

  /**
   * Shows staff names only while they belong to the task's organization.
   */
  public function assignee(TaskInterface $task): mixed {
    if ($task->get('assignee')->isEmpty()) {
      return $this->t('Unassigned');
    }
    $user = $task->get('assignee')->entity;
    $label = $user && $user->access('view') ? $user->getDisplayName() : $this->t('Assigned');
    $this->modules->alter('task_ui_assignee', $label, $task);
    return $label;
  }

  /**
   * Uses shared readiness; diagnostics containing foreign IDs stay private.
   */
  public function state(TaskInterface $task): mixed {
    return $this->stateLabel($this->readiness->evaluate($task)->state);
  }

  /**
   * Human-readable readiness labels without exposing dependency details.
   */
  private function stateLabel(string $state): mixed {
    return match ($state) {
      'active' => $this->t('Ready'),
      'pending' => $this->t('Scheduled'),
      'waiting' => $this->t('Blocked — waiting for required work'),
      'invalid' => $this->t('Needs manager attention'),
      'resolved' => $this->t('Resolved'),
      'closed' => $this->t('Closed'),
    };
  }

  /**
   * Formats separate due/deadline fields using the viewer's timezone.
   */
  public function date(TaskInterface $task, string $field, bool $overdue = FALSE): mixed {
    $value = $task->get($field)->value;
    if (!$value) {
      return $this->t('—');
    }
    $timestamp = (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
    $formatted = $this->dates->format($timestamp, 'short');
    return $overdue && $timestamp < $this->time->getCurrentTime()
      && !in_array($task->get('status')->value, ['resolved', 'closed'], TRUE)
      ? $this->t('@date — overdue', ['@date' => $formatted]) : $formatted;
  }

  /**
   * Links to a protected form; never changes assignment through GET.
   */
  public function actions(TaskInterface $task): array {
    $actions = [];
    $this->modules->alter('task_ui_task_actions', $actions, $task);
    return $actions;
  }

  /**
   * Applications opt reference fields into the list and task summary.
   */
  public function contextFields(): array {
    $fields = [];
    $this->modules->alter('task_ui_context_fields', $fields);
    return $fields;
  }

}
