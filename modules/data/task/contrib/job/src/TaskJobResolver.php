<?php

namespace Drupal\task_job;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Resolves the authoritative job reference for runtime consumers.
 */
class TaskJobResolver {

  public function __construct(protected JobVersionResolverInterface $versions, protected ModuleHandlerInterface $modules) {}

  /**
   * Resolves config jobs and allows content providers to supply their source.
   */
  public function resolve(FieldableEntityInterface $task): ?JobInterface {
    $job = NULL;
    if ($task->hasField('job') && !$task->get('job')->isEmpty()) {
      // New editor previews may carry unsaved definitions. Persisted work must
      // resolve storage again, including during delegated authorization.
      $job = $task->isNew() && $task->get('job_version')->isEmpty()
        ? $task->get('job')->entity
        : $this->versions->load($task->get('job')->target_id, $task->get('job_version')->value);
    }
    $this->modules->alter('task_job_resolve', $job, $task);
    return $job;
  }

}
