<?php

namespace Drupal\task;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Evaluates task readiness without changing task or service entities.
 */
class TaskReadiness {

  /**
   * Constructs the evaluator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {}

  /**
   * Evaluates supplied task values against current default referenced entities.
   *
   * The caller must supply the current task and enforce access separately.
   * Results are not cached: readiness can change with time or another entity.
   * Reasons may contain inaccessible entity IDs; filter them before display.
   */
  public function evaluate(TaskInterface $task): TaskReadinessResult {
    $reasons = [];
    $pending = FALSE;
    $blocked = FALSE;
    $status = $task->get('status')->value;
    $now = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $this->time->getCurrentTime());
    if ($task->get('start')->value && $task->get('start')->value > $now) {
      $pending = TRUE;
      $reasons[] = ['code' => 'future_start'];
    }
    if ($status === TaskInterface::STATUS_WAITING) {
      $blocked = TRUE;
      $reasons[] = ['code' => 'manual_hold'];
    }
    elseif (!in_array($status, [NULL, '', 'active', 'pending', 'resolved', 'closed'], TRUE)) {
      $blocked = TRUE;
      $reasons[] = ['code' => 'invalid_task_status', 'status' => $status];
    }

    $ids = [];
    foreach ($task->get('dependencies') as $item) {
      if ($item->target_id !== NULL) {
        $ids[] = $item->target_id;
      }
    }
    $dependencies = [];
    if ($ids) {
      $storage = $this->entityTypeManager->getStorage('task');
      $storage->resetCache($ids);
      $dependencies = $storage->loadMultiple($ids);
    }
    foreach ($task->get('dependencies') as $item) {
      $dependency = $dependencies[$item->target_id] ?? NULL;
      if (!$dependency || $dependency->get('status')->value !== TaskInterface::STATUS_RESOLVED) {
        $blocked = TRUE;
        $reasons[] = [
          'code' => $dependency ? 'dependency_unresolved' : 'dependency_missing',
          'target_id' => $item->target_id,
        ];
      }
    }

    if ($task->hasField('service') && !$task->get('service')->isEmpty()) {
      $id = $task->get('service')->target_id;
      $service = $id === NULL ? NULL : $this->entityTypeManager->getStorage('service')->loadUnchanged($id);
      if (!$service) {
        $blocked = TRUE;
        $reasons[] = ['code' => 'service_missing', 'target_id' => $id];
      }
      elseif ($service->get('status')->value === 'draft') {
        $pending = TRUE;
        $reasons[] = ['code' => 'service_draft', 'target_id' => $id];
      }
      elseif ($service->get('status')->value !== 'active') {
        $blocked = TRUE;
        $reasons[] = ['code' => 'service_inactive', 'target_id' => $id, 'status' => $service->get('status')->value];
      }
    }

    // Terminal disposition wins, then scheduling, then blocking reasons.
    $state = in_array($status, ['resolved', 'closed'], TRUE) ? $status : ($pending ? 'pending' : ($blocked ? 'blocked' : 'active'));
    return new TaskReadinessResult($state, $reasons);
  }

}
