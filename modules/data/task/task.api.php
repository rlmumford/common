<?php

/**
 * @file
 * Hooks provided by the task module.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\task\TaskInterface;

/**
 * Validates final task fields immediately before storage writes them.
 *
 * Runs after presave hooks, within the SQL storage transaction.
 * Implementations may acquire database locks and throw to abort a save.
 * Do not mutate the entity or perform external side effects. Validation
 * may run more than once if field post-save handlers request another write.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface $task
 *   The task being written.
 */
function hook_task_storage_prewrite(ContentEntityInterface $task) {
  \Drupal::service('example.task_validator')->validate($task);
}

/**
 * Contributes readiness decisions and invalidation recommendations for a task.
 *
 * Return an empty array when this module has no gate. Contributions add reasons
 * and cannot remove other modules' gates or override a terminal task status.
 * Terminal status wins, followed by invalid, pending, waiting, and active.
 * Invalid is a recommendation: evaluation itself never resolves a task. Task
 * saving/processing applies it as resolved with the invalid resolution.
 * Hooks may run during saves or independent evaluation. Do not mutate entities,
 * start work, or perform external side effects. Reload references as needed.
 * Readiness is not an access check or an atomic execution claim.
 *
 * @param \Drupal\task\TaskInterface $task
 *   The task being evaluated, which may be unsaved.
 *
 * @return array
 *   A list of reasons. Each requires a state (active, pending, waiting, or
 *   invalid) and a string code, preferably prefixed with the module name.
 *   Diagnostic values are allowed; check access before displaying references.
 */
function hook_task_readiness(TaskInterface $task): array {
  if (!\Drupal::service('example.approval')->isApproved($task)) {
    return [['state' => 'waiting', 'code' => 'example_approval_required']];
  }
  return [];
}
