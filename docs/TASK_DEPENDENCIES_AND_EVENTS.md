# Task dependencies, workflow events and replacement work

Design record — 30 September 2026.

## Implemented first slice

See [Task event dependencies](../modules/data/task/contrib/dependency/README.md)
for the actual APIs, integrations and tests. UUID subscriptions and indexed
bindings now support `activate` and `invalidate` actions, remembered match
receipts, ordinary job-trigger/template creation, Flexiform HTML/API editing and
explicit synchronous replacement migration selected by the caller. There is no
per-dependency replacement-following setting. Conditions are deferred.

The implementation is specifically the remembered-occurrence subscription model
with a persisted `met` flag. It does **not** implement continuous prerequisites or
prove simultaneous group satisfaction. Generic replacement matching requires
retargeting before the successor transition; durable replay and asynchronous
replacement-pending resolution remain future work. The broader alternatives below
are retained as design considerations, not claims about implemented behavior.

## Broader design record

This extends [Workflow architecture](WORKFLOW_ARCHITECTURE.md) and the P2/P7 work
in [the implementation plan](WORKFLOW_IMPLEMENTATION_PLAN.md). It records the
dependency/replacement requirements and a proposed way to share matching
with job triggers. Names below describe contracts, not committed PHP APIs.

## Confirmed requirements

- Saving a task that is active requests checklist processing. Workers reload and
  recheck readiness; automatic work must not require opening the task in a browser.
- Dependencies gate an existing task, allowing its metadata, assignments and
  planned dates to be prepared ahead of time. Triggers can create new tasks.
- Dependencies can reference entities other than tasks: for example a document
  awaiting approval or an appointment awaiting attendance/completion.
- Task-only dependencies previously relied on terminal resolution: repeating work
  creates a new task. Generalised targets can have reversible states, so event
  occurrence and current-state requirements are no longer equivalent.
- Mixed dependencies must have explicit temporal semantics. Current truth versus
  remembered group satisfaction remains under discussion; independent receipts
  cannot prove that prerequisites were true together.
- The dependency identifies the target entity, field/property and qualifying value.
  State Machine is optional; a normal scalar field must be sufficient.
- All dependencies must be satisfied. An unsatisfied dependency with a missing
  target or invalid configuration keeps work waiting with a diagnosable reason.
- Legacy task dependencies require `task.status = resolved`. `closed`,
  cancellation and a replacement relationship are not completion. Migration must
  explicitly preserve already-satisfied legacy dependencies.
- A dependency can follow replacement work. Rescheduling or a no-show must not
  accidentally unlock work that still requires the phone call to happen.
- Record dependency retargeting in operational history. Resolved tasks stay resolved.
- This complements triggers; it must not create duplicate tasks merely to wait
  for an event when the intended task already exists.

## Current implementation and the extraction boundary

`task/src/TaskReadiness.php` currently loads task targets and compares their
`status` with `resolved`. `Task::postSave()` directly saves dependent tasks after a
status change, querying the task-only reference field. Both need generalising.

The existing job trigger system already has an event vocabulary:
`entity_op:{entity_type}.{insert|update|delete}`. Entity hooks pass current and
original entity contexts to `JobTriggerManager::handleTrigger()`. The manager
checks the trigger and invokes `createTask()`. `JobTriggerInterface` also binds
plugins to a job, and the base implementation uses Entity Template to create work.

A dependency must not call `handleTrigger()` or `createTask()`, nor acquire a job
reference simply to watch an entity. Extract/reuse event description, typed
contexts, correlation and condition matching below that creation-specific layer.
Keep existing trigger plugins as adapters to job creation.

## Shared matching, two consumers

A useful model is **a dependency is an event/state subscription bound to contexts**.
A job trigger and an existing task can use the same event source and matching
configuration; what happens after a match differs:

| Shared match | Job trigger consumer | Dependency consumer |
| --- | --- | --- |
| Document becomes approved | Create a configured task | Record satisfaction of an existing task waiting on that document |
| Appointment completes | Create follow-up work | Satisfy the existing task's appointment requirement |
| Appointment is replaced | Apply configured creation behaviour, if any | Rebind an opted-in dependency to the explicit replacement |

Separate these concerns:

1. **Event source:** identifies an occurrence and exposes typed context definitions
   and values, including current/original entities where available. An appointment
   entity is a business object; a workflow event is a notification about a change.
2. **Match configuration:** event source ID, standard `context_mapping`, correlation
   to the bound target and optional Drupal condition plugin configuration. Reuse
   Typed Data Plus context handling and conditions rather than another expression
   language. Event matching is read-only and never creates or saves a task.
3. **Consumer:** job creation or reevaluation of an existing task dependency.
   Consumer-specific permissions, execution identity and effects stay separate.

Bind target identities durably. Match against the recorded occurrence facts and
its context bindings, not mutable state reloaded when a worker happens to run.
Reload entities for current authorization and execution checks; do not persist a
serialized entity as the live target. A matching
entity type is insufficient: an event about document B must not satisfy a wait
bound to document A. Keep event contexts and task/global contexts explicitly mapped
through the existing context system; do not merge unqualified names over each other.

A dependency references the shared event/match definition and its bindings, not a
mutable trigger instance inside an unrelated job. Initially the configuration can
be embedded, with a stable event plugin ID. A separate reusable configuration entity
is not needed merely to achieve code reuse. Existing job-version rules still govern
job-owned configuration; recorded matches must identify the binding/configuration
version against which they were evaluated.

## Temporal semantics — mixed dependencies need an explicit decision

The discussion first proposed current-state matching, then preferred a remembered
occurrence after registration to avoid depending on worker speed. The document-A /
task-B example exposes an additional requirement: the prerequisites may need to
be true together, not merely to have each been true at different times. The default
for generalised dependencies is therefore not yet settled. Do not implement either
choice as an implicit universal rule.

Task resolution was terminal in CounselKit: repeated work created a new task. For
such a monotonic prerequisite, remembering resolution is equivalent to observing
that it remains resolved. Document approval, appointment state and other reversible
properties do not have that guarantee.

Example: A is approved, A returns to draft, then B resolves. Independently remembered
occurrences would unlock the task although A-approved and B-resolved were never
true together. A current-state dependency group would remain blocked.

| Policy | Meaning | Effect of reversal |
| --- | --- | --- |
| Remembered occurrence | Each specified event occurred after registration | Does not erase that dependency's match |
| Continuous prerequisite | Every required predicate is true in the current authoritative state | Blocks unresolved work again |
| Remembered group match | All predicates were true together at a defined logical point | Retains the group's satisfaction after later reversal |

The third option can preserve a brief valid overlap even if workers run later,
but is a distinct product decision and needs ordered occurrence facts to prove the
overlap. It must not be approximated by independent per-dependency receipts. A
historical group match also does not assert that it remains safe to execute work
now; any continuing execution preconditions must still be checked separately.

Proposed baseline to discuss: retain task resolution as terminal, use current
prerequisites for reversible state requirements, and offer remembered occurrences
only where the workflow explicitly means "this happened". That proposal is not yet
an agreed default. Separately settle whether loss of a prerequisite suspends an
already-active unresolved task. No policy silently reopens a resolved task or
undoes checklist outcomes and external actions already taken.

### Shared infrastructure regardless of policy

Share event sources, typed contexts and matching definitions with triggers.
An appointment entity is the bound business object; a notification is an occurrence
about that object. A save that leaves a state unchanged is not a fresh transition.
Creation directly in a qualifying state requires an explicit source convention.

Record source occurrences and dispatch intent with their transactions; rollbacks
publish nothing. Use durable ordering/identity rather than second-resolution times
alone. Retain relevant before/after facts and subject identity without dumping whole
entities or confidential provider payloads into a broadly readable log.

For remembered occurrences, persist registration boundaries and idempotent match
receipts. Match against occurrence-time facts, not whatever the entity contains
when a worker runs. Mutable related contexts or Views results need an occurrence-
time decision/snapshot if they participate in that match. For continuous policies,
notifications request fresh authoritative evaluation; past match receipts do not
stand in for the current truth. Consistent group evaluation and final worker
readiness checks are needed, rather than combining values read at unrelated times.

Registration/index races, delayed delivery, replay and retention must have explicit
contracts. A source needs capabilities matching the selected policy: an occurrence-
only source cannot pretend to support a continuous predicate, nor can a mutable
current-state read reconstruct a missed historical event. Existing start-date and
immediate-service gates remain continuous readiness checks.

## Replacement following and rolling forward

Store an explicit policy: stay on this target, or follow its replacement. The
source integration supplies the replacement relationship; Task must not guess
from a cancellation status, matching dates or whichever appointment is newest.
No phone, calendar or chat dependency is introduced into Common.

A replacement resolver reports one of: a known replacement, replacement expected
but not yet available, no replacement, or ambiguous/invalid replacement data.
The dependency retains its original target for provenance and a current binding
for evaluation/indexing, plus a binding version to reject stale notifications.
The unchanged requirement follows the new binding; if a replacement has another
entity type or incompatible field, an explicit integration mapping is required.
Never silently substitute an unrelated status field or value.

For an appointment A replaced by B:

1. Record the replacement relationship or durable replacement-pending intent before
   publishing readiness work. A cancellation/no-show alone does not satisfy the
   requirement or remove it.
2. Rebind the unsatisfied dependency to B, update its reverse lookup and record the
   audit event atomically. Keep it waiting while replacement is pending or ambiguous.
3. Apply the selected policy to B: reevaluate its current value for continuous
   prerequisites, or consider eligible recorded occurrences/group matches for a
   historical policy. Do not silently change the dependency's temporal meaning.
4. Follow B to C similarly if it is replaced again. Detect loops and impose a
   bounded traversal limit; do not pick arbitrarily between multiple successors.

Cancellation without replacement is an explicit workflow decision: retain the
wait, cancel the dependent task, or deliberately remove/change its requirement.
The dependency engine does not make that business decision implicitly.

A notification about A after its effective replacement cannot satisfy a binding to
B. Retargeting preserves the requirement and its policy. For an occurrence policy,
retain the original waiting boundary so qualifying B events after registration but
before retargeting processing are not lost. An A receipt cannot simply be copied
to B. For continuous prerequisites, evaluate B now; its earlier state is not enough.

For remembered matches, completion and replacement must be interpreted in durable
source order, not delivery order. Completion before replacement and replacement
before completion can have different meanings, but fast and delayed workers must
agree. The chosen group policy determines whether a recorded match is already
final or still participates in an unsatisfied group; this must be settled before
implementing retargeting of partially satisfied mixed dependencies.

Keep existing task-to-task self/cycle checks; these do not prove the absence of
all logical cycles involving other business entities.

Audit records identify the dependent task/dependency, old and new targets, actor
or initiating occurrence, reason, timestamp and binding version. Keep this distinct
from checklist item attempt history; changing a dependency is not an item attempt.
Respect target access when displaying reasons or history across entity boundaries.

## Notification, reevaluation and processing

Keep the common event/matching contracts below `task_job`: the Task module must
not depend on the job-creation module that already depends on Task. Start with
small services/contracts in Task, with an extractable boundary if other consumers
later need them. Integrations provide source-specific state and replacement logic.

Maintain a reverse index by target entity type/identity, with field/source details
where useful, to locate affected dependency bindings without scanning every task.
Update it on task dependency edits, retargeting and deletion. Job-trigger indexing
and dependency subscriptions can share the event description while retaining their
different lookup needs and retention rules.

Entity changes notify both consumers. Relevant saves, deletion and replacement
updates request dependency reevaluation; reevaluation recomputes all readiness
inputs, including start date and the immediate service. Dependencies produce
`waiting`; future starts and draft immediate services produce `pending`. Preserve
the existing invalidation and terminal-state precedence.

Persist requests with the source change, and dispatch after commit. A rollback
must not leave an executable notification. Deduplicate deliveries and use a
monotonic requested/processed generation (or equivalent) so a save arriving during
processing cannot be lost. Do not model the entire mechanism as a transient PHP
callback or promise. Occurrence subscriptions need durable registration/receipt recording and
replay/reconciliation after missed notifications.

An active task save requests checklist processing. When item results change gates
or outcomes, request further checklist evaluation through the same durable path;
do not depend on a browser refresh or a coincidental later task save. Workers load
current state, reevaluate readiness and run actionable work under an explicitly
authorised execution identity. The source entity's saver is an initiator, not an
automatic grant to run every dependent task as that user or as an administrator.

Avoid recursive processing on save: separate reevaluation, persistence and execution;
coalesce requests and suppress only demonstrably redundant processor-originated
writes. A newer external save must survive that suppression. Existing iteration
claims/fences still protect item work. Job creation also needs consumer-specific
idempotency; a single notification can legitimately both create a different task
and reevaluate an existing one.

## Delivery sequence and acceptance

The next implementation slice is active-task-save and item-result checklist
processing, using existing task dependencies. That work can proceed independently
of this generalised dependency design. The sequence below describes the dependency
track; it is not a prerequisite for connecting existing tasks to processing.

1. Settle continuous versus remembered dependency/group semantics and the effect
   of reversal on active unresolved work. Then extract the shared event/context
   matching boundary from job creation, keeping
   existing entity-operation triggers working. Define immutable matching facts,
   durable occurrence identity/order and consumer-specific access semantics.
2. Implement the selected temporal policy with durable notification/registration
   and reconciliation. Add receipts/ordered facts where required by remembered
   semantics, and indexed entity-state dependency subscriptions.
3. Add replacement resolution, pending replacement handling, atomic retargeting
   and dependency history, with a generic test integration rather than phone code.
   Test retargeting of partially satisfied groups under the chosen policy.
4. Migrate old task references: record existing resolved prerequisites as explicitly
   grandfathered satisfaction where appropriate, retain unresolved waits, and
   preserve task-cycle checks and readiness/service precedence. Do not invent old
   event receipts or leave already-satisfied dependencies waiting for another event.
5. Connect active task saves and item-result changes to durable checklist processing,
   with commit safety, coalescing and explicit execution identity.

Required tests include:

- A-approved, A-revoked, then B-resolved does not count as simultaneous truth.
  Test the selected policy explicitly rather than passing with independent receipts.
- A and B overlap briefly, then A reverses before processing: distinguish current
  readiness from a remembered group match, with the agreed activation/reversal rule.
- Repeating resolved task work creates a new task. No dependency reevaluation
  silently reopens an old resolved task or undoes completed actions.
- One event description supports a job trigger and a dependency with different
  effects, correct context correlation and consumer-specific access checks.
- Completion-before-replacement and replacement-before-completion produce their
  respective results even when notifications arrive in reverse order.
- Appointment A is replaced by B, then C; no intermediate activation occurs;
  completion of C satisfies the wait. Late completion of A/B is irrelevant.
- Replacement arrives in a later transaction; cancellation without a replacement,
  missing/deleted targets, ambiguity, incompatible types and cycles remain blocked.
- A replacement qualifies before retargeting is processed, then changes again:
  the chosen current/remembered policy determines the result, not accidental mixing
  of those policies. Failed retargeting rolls back binding, index and audit together.
- Reverse delivery order, registration/index races, duplicate notification, source
  transaction rollback, event retention, worker interruption and
  concurrent saves neither lose evaluation nor create duplicate execution attempts.
- Item A completes, its outcome makes B actionable, and B runs without opening the
  task. Task readiness still gates execution and access changes are rechecked.
- Upgrading existing dependencies preserves target identity and existing
  satisfaction; unresolved legacy references register for future resolution. Retargeting history and blocking reasons do not disclose hidden data.

## Implemented follow-up: configurable trigger actions

Job event matching now dispatches a configurable action, defaulting to task
creation. `task_dependency_job` provides an explicit entity-replacement event and
an action which retargets unmet dependencies on unfinished tasks of the configured
logical job. Its original/replacement mappings use the standard context handler.
There is no per-dependency replacement flag. The source workflow remains
responsible for reporting replacements in its transaction. See the
[job integration guide](../modules/data/task/contrib/dependency/modules/job/README.md)
for configuration, dispatch order, extension points and delivery limitations.
