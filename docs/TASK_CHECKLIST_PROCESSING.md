# Automatic processing of task checklists

Saving an active task records a durable request to evaluate its checklist.
Changing a persisted item's status or outcomes records another request for its
owning task. Working-state checkpoints alone do not request evaluation.

For example, an automatic background item produces a document outcome. Its save
requests another pass through the task checklist; a later item can then consume
that outcome without anybody opening the task. Existing task dependencies also
work: resolving a prerequisite saves its dependants, and newly active tasks
request processing. Generalised entity/event dependencies remain a separate
slice; their design is in [TASK_DEPENDENCIES_AND_EVENTS.md](TASK_DEPENDENCIES_AND_EVENTS.md).

## Save, dispatch and execution

`task_checklist.request_storage` records requests in the same SQL transaction as
the source save. A rollback removes both. The save hook does not run handlers or
publish directly to a potentially nontransactional queue: a rollback must not
undo the audit of an external action that has already happened.

`task_checklist.scheduler` dispatches committed requests to the Drupal Queue API
queue `task_checklist_process`. Cron dispatches up to 50 request rows, oldest ID
first, combining the selected requests for the same task and execution account.
The dispatch limit is configurable by the service caller (1–100). Dispatch and
execution reject calls inside an open database transaction.

The worker acknowledges only the exact request IDs it received. Saves during
processing, including lower IDs whose transactions commit late, remain pending.
A five-minute reservation allows subsequent scheduler runs to recover messages
lost between reserving and enqueueing, or by the transport. Duplicate deliveries
whose requests are already acknowledged are harmless. A task-level lock prevents
normal overlapping whole-checklist evaluations; a busy worker leaves its request
pending for recovery.

The task processor reloads current task readiness, then uses the existing
checklist processor and item executor. Short automatic items can complete in that
worker request, with their normal attempt audit. Background and yielding items
use the existing item iteration queue. Failed items require an explicit retry;
a whole-checklist wake-up does not start a fresh attempt for them.

## Execution identity and access

Requests remember the saving account. For anonymous cron/system saves, the task's
`creator` is the candidate execution account. An anonymous creator cannot schedule
automatic work through these hooks. Integrations needing another identity should
save under their intended account using Drupal's account switcher.

The worker reloads the candidate user and role permissions and checks that the
account remains active, can view/update the task, and can view/edit its checklist
field. Each item still uses its existing execution-access and claim checks.
Missing tasks, changed UUIDs, blocked/deleted users and denied access retire that
request; a later authorised save can request processing again. The account and
optional execution environment are restored on success and exceptions. Other
exceptions propagate so delivery can be retried, without implicitly retrying a
failed item attempt.

## Operations and limits

Run database updates to install `task_checklist_request` on existing sites; fresh
installs create it through `hook_schema()`. Existing tasks are not bulk-enqueued.

Regular Drupal cron both dispatches requests and runs the queue. Processing latency
therefore depends on cron frequency. For a CLI worker arrangement, schedule calls
to `task_checklist.scheduler->dispatch()` and consume `task_checklist_process`
alongside the existing checklist item queue. Merely running a queue consumer does
not dispatch new outbox rows. This change does not provision a worker container or
promise immediate dispatch after a save.

The queue transport is configurable through Drupal. The scheduler depends on
`TaskChecklistRequestStorageInterface`, not SQL queries; a replacement storage
implementation must preserve transactional recording and exact acknowledgment.
Swapping SQL recording for an ordinary Redis write would lose rollback safety.

Delivery is at least once. The task evaluation lock expires after 15 minutes;
it is not an exactly-once guarantee for arbitrarily long legacy action methods.
Use the audited item executor, resumable handlers and idempotent external actions
for long-running work. Follow-up requests can need a later cron pass. This slice
does not change the UI, generalise dependencies, or reopen resolved tasks.

## Verification

`TaskChecklistWakeupTest` covers coalescing, rollback, background outcome chaining,
dependency activation under an anonymous worker, lost delivery, out-of-order
request visibility, postponed work, blocked users, permission revocation, failed
handlers and duplicate delivery, account restoration, and schema installation.
Run it against SQLite and MySQL/MariaDB. Existing task readiness, versioned job
checklists and checklist processor tests cover the surrounding integration.
