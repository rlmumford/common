# Task

Reusable tasks for Drupal 10/11, extracted from `rlmumford/common` on `2.x`.
The source of truth remains the common repository; the `rlmumford/task` GitHub
repository is an automated split output.

Enable `task_job` for jobs, task contexts, and programmable checklists. Enable
the separate `service` and `note` modules for work containers and comments.
The supporting packages are `rlmumford/checklist`, `plugin_reference`,
`typed_data_plus`.

## Behavior

- A task is one unit of work; a job configures its triggers, checklist and resources.
- A service groups work. The task's `root` groups a chain and its comments;
  it is distinct from the service tree and from dependency ordering.
- Explicit assignees take precedence. Jobs can default to an active service
  manager, an active task creator, or no assignee. The `task.select_assignee`
  event remains the extension point for more sophisticated assignment rules.
- Stored `pending` includes scheduled and blocked work; use readiness for execution.
  Drupal cron queues due tasks, and the `task_scheduled` worker rechecks them.
  Resolving a prerequisite also rechecks its dependents. `waiting` is a manual
  hold and is not automatically released. Queue scans rotate in batches of 100.
- Notes on a task inherit its root and service references.
- Interactive checklist routes and submissions require update access to the
  containing entity. Task permissions distinguish assigned and all tasks.

This is the initial reusable foundation, not a complete port of Drupal 7
CounselKit. Its legal-specific checklist handlers, smart board, recurrence,
assignment groups/condition expressions, resource-pane UX, and full audit
semantics remain follow-up work. No existing CounselKit task data is migrated.

## Task readiness

`task.readiness->evaluate($task)` returns `TaskReadinessResult` with a `state`
and all `reasons`. Supply the current task; referenced dependencies and the
immediate service are reloaded from storage. The evaluator does not save entities.

Precedence is resolved/closed, then pending (future start or draft immediate
service), then blocked (manual hold, unresolved/missing dependency, or non-active
or missing immediate service), otherwise active. Due dates and deadlines do not
block execution. Only `resolved` satisfies a dependency: **closed dependencies
now block work**, unlike the earlier implementation. Ancestors never gate tasks.
No service reference means no service gate.

The existing stored `status` still projects open work into pending/active for
legacy queue queries; `waiting` remains an explicit hold. It can lag a service
change. Runtime readiness is authoritative for processing, and distinguishes
pending from blocked without recursively saving tasks when a service changes.
A separate persisted intent model and readiness query/index support remain open.

The checklist processor now reloads the task and requires active readiness before
processing. Cron's worker also reloads the current task, preserving holds and
terminal states. This fixes processing of pending tasks whose start date passed
but whose dependencies remain unresolved. Draft-service work is reconsidered by
the existing pending-task cron queue after the service becomes active.

Readiness is a point-in-time check, not an access check or an execution lock.
The caller must enforce permissions and execution identity. Do not cache results
or expose referenced IDs in reasons without checking access. Durable execution
claims, per-item gates, and interactive action enforcement remain later work.

## Tests

In a Drupal consumer with these modules installed on disk, plus `service`,
`note`, and `drupal/core-dev`:

```sh
SIMPLETEST_DB=mysql://user:pass@localhost/test_db vendor/bin/phpunit \
  --bootstrap "$(pwd)/web/core/tests/bootstrap.php" \
  web/modules/contrib/task/tests/src/Kernel
```

Use a local test database. Kernel tests use isolated prefixed tables.
