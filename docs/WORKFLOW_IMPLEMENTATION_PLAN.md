# Workflow framework implementation plan

Status: P0 design baseline recorded; P1–P4 development in progress; P5–P9 planned. Updated 18 September 2026.

This plan implements the requirements in [Workflow architecture](WORKFLOW_ARCHITECTURE.md)
within `rlmumford/common` on `2.x`. CounselKit `11.5.x` is the behavioral reference.
The current split packages are a foundation, not completion of this plan.

The [interaction contract](CHECKLIST_INTERACTION_CONTRACT.md) specifies item reads,
action progress, shared UI/API/AI workspaces, user ownership and explicit takeover.
Its locks, state projection and HTTP endpoints remain implementation work.

Application integration, including further Christian Jobs UI work, follows the
reusable framework. No production deployment or CounselKit data migration is
implied by this plan. Each phase should produce independently reviewable changes,
passing tests and updated consumer documentation before its dependent phase starts.

## Working rules

- Develop in Common; publish through existing split workflows. Never develop fixes
  independently in the split repositories.
- Keep Drupal module dependencies acyclic and optional integrations separately
  enabled. Test with an independent consumer, not only a development monorepo.
- Prefer Drupal typed data, plugins, config schema, access results and services over
  copying Drupal 7 compatibility layers. Preserve required behavior rather than
  every historical implementation choice.
- Specify and test upgrades for installed packages whenever stored fields/config
  change. A fresh install alone is insufficient.
- Maintain an explicit requirements-to-tests inventory. Existing tests must not be
  relabelled as proof of the larger framework.
- Review entity, lifecycle and execution designs before implementation. Resolve a
  phase's blocking decisions in its design record; do not create blanket approval
  gates for routine implementation choices.
- Keep Common independent of Christian Jobs roles, legal fields, phone and chat.
  Operational history is required; configurable recovery workflows are excluded.

## Phase map

| Phase | Deliverable | Prerequisites |
| --- | --- | --- |
| P0 | Contract inventory and lifecycle decisions | None |
| P1 | Shared data fetcher, filters and condition engine | P0 |
| P2 | Nested services and task lifecycle | P0; P1 for condition-based policies |
| P3 | Checklist contracts, state, outcomes and history | P0; P1 for typed contexts/gates |
| P4 | Checklist processing and decision items | P1, P3; P2 for task integration |
| P5 | Templates, providers and derivative reconciliation | P4 |
| P6 | Resource workspace and interactive/API parity | P4; P5 for dynamic UI coverage |
| P7 | Durable attempts and Messenger execution | P2–P4; regression coverage for P5–P6 |
| P8 | AI provider reuse and task-owned sessions/runs | P3, P4, P7 |
| P9 | Packaging, upgrade and reference-workflow release | All applicable phases |

P2 and the basic P3 model can proceed independently after P0. Resource rendering
and background execution can be developed separately once their shared execution
contracts are fixed. AI should not be required to install or use checklists.

## P0 — Inventory contracts and settle lifecycle boundaries

Deliverables:

- Catalogue the 11.5 plugin interfaces, processor entry points, item providers,
  conditions, outcomes/state, resource hooks, queues and execution-user policies.
  Record each as reuse, adapt, defer or exclude, with a source revision/path.
- Map the Entity Template extended fetcher, filters and dependencies before moving
  them. Determine what belongs in TypedDataPlus and how existing callers migrate.
- Specify task statuses, service transitions, item/attempt states, retry/reset and
  concurrent-edit rules. Distinguish persisted work state from computed readiness.
- Decide the open hierarchy policies listed below and the public extension points.
- Record dependency/version constraints for supported Drupal/PHP versions and the
  selected Drupal AI, Tool API and Messenger integrations. Prototype compatibility
  rather than relying on project-page claims.

Exit criteria: every agreed requirement has an owning package and planned test;
no circular dependency; migration-sensitive decisions are documented before schema
changes. Unresolved optional features are explicitly deferred, not silently omitted.

P0 evidence: [contracts, source inventory and lifecycle decisions](WORKFLOW_PHASE_0.md),
including a reproducible dependency probe and planned acceptance scenarios.
Runtime integration proofs remain in their owning implementation phases.

## P1 — TypedDataPlus fetching and condition evaluation

Implementation and its Coder/PHPUnit CI live in the Drupal.org
[Typed Data Plus repository](https://git.drupalcode.org/project/typed_data_plus).
Common owns this integration plan and currently consumes `2.0.x-dev` from the
official Git repository through Composer;
it does not contain or mirror Typed Data Plus source.

First slice: an independently enabled `typed_data_plus` module exposes a filtered
fetcher and definition-only API, strict quoted-argument parsing and wrapped-filter
compatibility. It coexists with Entity Template using a separate service ID.
See the [package guide](https://git.drupalcode.org/project/typed_data_plus/-/blob/2.0.x/README.md). Coordinated
Entity Template migration is merged upstream. Typed Data Plus MR !1 is also merged
into `2.0.x` (not yet released). This slice adds
strict grouped parsing, data predicates, typed references, expected-definition
validation and cacheable explanations. It exposes `condition_string`,
`condition_and` and `condition_or` as Drupal condition plugins, with dynamic
context contracts and filtered local/global context assignment. Binary
`condition_xor` and `condition_xand` (XNOR) require exactly two operands; XOR
succeeds when they differ, XAnd when they agree. Larger expressions nest groups.
`condition_constant:true` and `condition_constant:false` are separately selectable
TRUE/FALSE gates without contexts or a value setting.
The original context-assignment submodule provides site-wide integration. Filter
extraction, Views, the remaining grammar/adapters and component conditions remain
open; P1 is not complete.

Deliverables:

- Extract/adapt the extended data fetcher and filter registry from Entity Template.
  Preserve existing filter behavior, list handling and typed-data definitions.
- Store checklist gates as Drupal condition plugin configurations. A condition
  string is one plugin; AND/OR groups compose ordinary core/contrib conditions.
  Supply expected contexts during configuration and fresh contexts at runtime,
  including global provider mappings. The containing editor owns group editing.
- Implement the shared condition parser/evaluator, extension interfaces, validation
  and unmet-condition explanations. Inventory grammar compatibility with 11.5.
- Integrate typed property/filter traversal, boolean composition, missing values
  and user context. Add Views-result checks as an optional Views integration with
  explicit contextual arguments and execution/access semantics.
- Add checklist-specific predicates in checklist/task integrations, not in the
  low-level evaluator. Add conditions to Entity Template components as a consumer
  of the same engine.
- Provide a compatibility path for existing Entity Template fetcher callers and
  context-assignment configuration. Document Composer repository requirements;
  Typed Data Plus publication on Drupal.org is required before releasing the
Entity Template dependency; see [publication steps](TYPED_DATA_PLUS_PUBLICATION.md).

Acceptance:

- Tests cover nested boolean expressions, negation, missing entities/outcomes,
  typed comparisons, filter chains and Views with arguments and user-dependent
  results. Invalid expressions report configuration errors.
- Conditions re-evaluate after context mutations; cached results cannot leak between
  users or release newly blocked work.
- Entity Template component conditions and checklist gates use the same fetcher and
  evaluator. Standalone TypedDataPlus does not require Entity Template.

## P2 — Nested services and task lifecycle

Traversal foundation implemented: `service.hierarchy` and lazy `service_reference`
`all`/`root` properties share cycle-safe resolution, explicit missing-parent errors,
and support unsaved graphs. The service kernel suite covers multilevel trees,
empty references, cycles, missing ancestors and retained properties after saved
moves. Write protection now serializes service saves/deletes and task attachments
through a transaction-held database mutex. Storage checks final references, scope
policies apply to direct saves, and `service.mover` provides an authorized move API.
Deletion refuses services with current children/tasks. Tests cover scope/access
denial, stale opposite moves, failed-save rollback, schema updates, and competing
connections holding the lock through outer commit. MySQL/MariaDB permission/scope
reads require READ COMMITTED isolation. See `modules/data/service/README.md` for
setup, supported write paths and limits. Entity constraints now provide parent
and scope feedback before saving; save-time hierarchy errors rebuild the form.
Computed ancestry properties carry owner and service-list cache tags so ancestor
moves invalidate dependent output without recursive descendant saves. Fetcher
consumers also need Typed Data Plus's computed-property metadata fix (MR !4).
Service status storage now provides draft/active/complete/cancelled/superseded,
with new services defaulting to draft. The old boolean field is replaced without
legacy migration: no sites are known to use the module. Development installations
using the old schema need a fresh installation.
Task readiness now evaluates scheduling, strict resolved-only dependencies,
and module-contributed gates, reporting every reason alongside its primary state.
The service module contributes only the immediate-service gate through
`TaskReadinessEvent` subscribers. Postponement uses the start date, with no manual hold.
Subscribers contribute active/pending/waiting/invalid reasons. Evaluation is read-only;
saving/processing applies invalid as resolved with an invalid resolution, while
preserving existing terminal outcomes. Cancelled services wait by default;
consumer policy decides which tasks should be invalidated. The checklist processor and queue worker reload current tasks;
processing requires active readiness. Stored pending/waiting/active is a projection refreshed on save and by cron;
readiness reads current gates. Resolution timestamps now normalize on every save,
including direct writes and presave status changes; reopening clears the timestamp
and repeated resolution preserves it. Explicit helper times use UTC. Query support
and durable resolution history remain open. Service transition APIs/history are deferred while task work takes
priority; no state-machine dependency is introduced.

### Existing base

`Service::baseFieldDefinitions()` already defines a service-to-service `service`
reference. `ServiceReferenceItem` exposes computed `root` and `all` properties
through the shared traversal resolver. Cycle and missing-parent detection now
protect reads; transaction-held write guards protect saves and deletes. Do not add a second
parent field without a deliberate compatibility/migration decision.

### Hierarchy contract

Support a forest of services: each service has at most one parent and may have many
children. A task references its immediate service; the service hierarchy and task
root remain distinct. Preserve or migrate existing `service`, `root` and `all`
property paths deliberately because templates/conditions can consume them.

Deliverables:

- Explicit parent, root, ancestor and child APIs; define ordering and whether an
  ancestor-chain result includes the referenced service itself.
- Reject self-parenting and descendant-parenting. Bound traversal safely for legacy
  cycles, missing parents and unsaved references; handle concurrent reparenting.
- Define reparent/delete behavior, access validation and cache invalidation. Root
  and ancestor data must refresh for descendants after a move.
- Add Draft, Active/In Progress, Complete, Cancelled and Superseded lifecycle values,
  replacing the unused boolean `state`, with history and transition events.
- Implement task readiness from schedule, strict resolved-only dependencies and
  service gates. Re-evaluate affected work on transitions without a long recursive
  save cascade. Enforce resolution timestamps across form/API/programmatic paths.
- Preserve explicit assignees and support extensible assignment policies, including
  condition-based rules. Specify manager fallback through the hierarchy if desired.

Hierarchy decisions and remaining implementation policies:

| Question | Decision or proposed policy |
| --- | --- |
| Does an ancestor in draft block descendant tasks? | No. Confirmed: gate only on the immediate service; ancestor statuses do not independently affect task readiness. |
| Do parent transitions change child statuses? | Leave child statuses unchanged unless an explicit workflow changes them. |
| Does parent completion require children complete? | Explicit completion policy, not an accidental consequence of nesting. |
| Are manager, recipients or permissions inherited? | No implicit inheritance. Separate optional assignment fallback from authorization. |
| Can a service move across organization boundaries? | Validate through a scope-policy extension; a move must not silently expose its tasks, notes or descendants. Common must not hardcode CounselKit firm entities. |
| Can a parent be deleted with children/tasks? | Refuse deletion with current child-service/task references; explicitly detach/delete dependents first. |
| Is legacy service migration required? | No known sites use the module; replace the boolean field without preserving or mapping old services. |

Acceptance: multilevel trees, independent roots, cycles, reparenting, stale root
caches and unauthorized moves are tested. Task gates react to the chosen hierarchy
policy, including an active immediate service beneath draft or terminal ancestors.
Every service transition has tested task/child effects, including no-effect
cases. Upgrade tests preserve existing references. State precedence, postponed starts,
closed-versus-resolved compatibility and reopening semantics are explicit.

## P3 — Checklist contracts, outcomes, state and operational history

Outcome-context foundation: configuration contexts retain complex/list/entity
outcome definitions, and runtime contexts retain typed values and item cache tags.
Automatic processing and completion checks now prepare fresh handler contexts
through Typed Data Plus before evaluation. Missing required values defer work;
invalid mappings surface as configuration errors. A standalone checklist kernel
suite exercises sequential outcome consumption, stale-value removal, filtered
selectors and global providers. Persisted list outcomes require Typed Data Plus
[MR !5](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/5).
The built-in create-entity action and form now share entity/outcome/completion
handling. Automatic creation respects the configured bundle and publishes the
created entity for downstream contexts, including after checklist reload.
Shared operation dispatch now checks host update access, refreshes runtime
contexts, enforces incomplete status and item gates, and checks current operation
discovery before delegating validation/persistence to the handler. Kernel coverage
includes missing/changed contexts, changed users/gates, unsupported and terminal
items, hidden operations, configuration errors and persisted decision outcomes.
The entity-based resolver now accepts a host object and field/delta with entity
and field access checks, verifies the checklist's host type, preserves unsaved
state, and avoids implicit reloads or tempstore substitution. New hosts use create
access; callers own entity loading and workspace selection.
Item queries are scoped by checklist type to isolate matching IDs on different host
types. Tests cover multiple fields/deltas, access denial, unsaved hosts and state,
and conflicting tempstore.
Adapters still supply the current checklist/account; shared workspace composition,
claims, execution identities and attempts remain open.

Opt-in working-state storage is now implemented through
`StatefulChecklistItemHandlerInterface::stateDefinitions()` and the item's separate
internal `state` field. State is retained on failure and cleared by completion
helpers and storage after presave hooks, preserving outcomes. Scalar/list/map/entity
state, unsaved-host tempstore, safe progress projection, excluded outcome contexts
and upgrades from the previous schema have kernel coverage. Ordinary field access
does not expose raw state. This is not yet a versioned attempt or public reset API;
operational history, ownership and stale-result protection remain open.

The internal `checklist.attempt_journal` now stores distinct attempts and append-only
transition metadata, with item UUID identity, entry path/operation, initiator and
executor, successor links and timestamps. A unique item head plus conditional
version writes reject stale starts/transitions. Resume and fresh are recorded
intents only: the journal does not reset item state or coordinate execution. Tests
cover lifecycle, successor history, SQL conflict paths, rollback and installation /
upgrade. Execution-path integration, workspace ownership, coordinated item-state
application, access-filtered history and retention policy remain open. Worker
claim primitives are described under P4.

Deliverables:

- Shared action methods, action forms and schema-defined action operations. Keep
  business logic outside form submit handlers and apply common gates to all paths.
- Expected-outcome definitions feeding configuration-time typed contexts; actual
  item outcomes feeding runtime contexts. No duplicate task outcome storage.
- Opt-in intermediate-state API, separate from outcomes. Specify shared versus
  user-owned state and concurrency semantics; do not assume simultaneous human
  turns are already supported by the D7 implementation.
- Durable execution-attempt history: initiator, executor, status/transition times,
  action/completion method, errors and links to results. Define retention/access.
- Confirmed failure policy: retain intermediate state for explicit resume. Resume
  creates a successor attempt using retained state; start-fresh creates a new attempt
  and clears working state while preserving history. Clear state on success.
  Optional redacted diagnostics belong in restricted history, not normal outcomes.
- Plugin/configuration discovery schemas and configuration-time validation.

Acceptance: forms, APIs and automatic actions produce equivalent domain effects;
invalid inputs and access failures do not mutate outcomes; expected contexts are
available before execution; state survives request boundaries; failure, resume,
reset and successful cleanup follow the chosen policy; history remains intact.

## P4 — Processor, conditions and decision plugins

Completion foundation: nullable applicability is preserved from handlers through
items. Unknown applicability blocks execution and completion. Processing reuses
`isCompletable()` after actions, so optional manual work does not block and earlier
items are re-evaluated against changed gates. Tests cover newly applicable earlier
work, removed requirements, and required unfinished/failed work.

Native condition gates now cover applicability, requiredness and actionability,
including same-pass outcome selectors and action/form rechecks. Configuration UI,
execution identity and claim integration remain open. The integration
requires the Typed Data Plus discovery and missing-context fixes in
[MR !6](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/6).

Decision foundation: the `decision` handler now supports named choices, per-option
condition plugins, required reasons and persisted typed outcomes. Decision values
use the labelled string enum in
[Typed Data Plus !7](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/7);
the machine string remains the stored value and context value. Definitions are
reconstructed from each persisted item's handler configuration snapshot, without
per-outcome definition copies. Versioning plugin implementations is deferred; code
changes must preserve compatibility with persisted outcomes. Loading retained
values and validating them against current constraints are separate operations. The action form
and schema-described `choose` operation share validation and completion, including
host update access and gate rechecks. Kernel tests cover denied/stale choices,
invalid inputs, reason validation and downstream outcome use after reload.
Action forms default to separate choice buttons, with radios/select alternatives.
All presentations preserve the shared validation and AJAX completion path.
AI choice, HTTP/tool adapters, generated items and concurrent execution remain open.

Deliverables:

- Port applicability, requiredness, actionability, item dependencies and explicit
  checklist completion conditions, including queued/failed/blocked handling.
- Recheck execution identity and access; separate audiences/visibility from the
  complete item set used by processing. Hidden automatic work must progress.
- Decision plugins with named options, availability conditions and durable outcomes.
  Validate availability at execution, not just when rendering the choices.
- Structured action results and delayed continuations; blocked explanations;
  deterministic dependency order and cycle/error handling.
- Enforce processing/edit locks and stale-state reload contracts before introducing
  parallel workers. Keep shared processing independent of the dispatch backend.

Acceptance: no resolution while required work is running, queued, failed or
lock-contested unless an explicitly designed completion policy permits it; changing
an outcome refreshes downstream gates; inaccessible/hidden options cannot be invoked
through APIs; all three action paths use the same identity and condition semantics.

Worker iteration coordination is now available through
`checklist.attempt_claims`: claim, token-rotating renewal, delayed yield via commit,
terminal commit and explicit expiry. The journal prevents bypassing active claims
or due times. Local result writes and history commit together; stale/expired workers
cannot apply results through this API. Expired running work is not automatically
re-executed. Kernel coverage exercises repeated iterations, item-state persistence,
late writes, independent items, transaction rollback and the schema upgrade.
The initial handler runner and Queue API scheduler are described below. Workspace
ownership and integration across existing interactive paths remain open.

The initial automatic handler runner now invokes an opt-in `actionIteration()`
under the stored active executor and restores caller identity on every exit path.
It reloads and rechecks host/field/item access, conditions and mapped contexts before
execution and result application. Typed result changes, item disposition and attempt
history commit under the claim. Waiting preserves state and the attempt identity;
success clears state; failure retains it. Legacy processing excludes iterative
handlers. This first adapter supports saved autonomous items on single-value,
untranslatable fields of non-revisionable hosts and initial action attempts only.
Authorized retry resets, unsaved workspaces and interactive ownership remain open. Kernel tests exercise cross-request continuation, failure,
identity restoration and in-flight changes to permissions, contexts and state.

Queue API delivery now schedules already-authorized initial action attempts from
cron through `checklist.iteration_scheduler`. Selection/reservation is behind
`ChecklistAttemptDispatchStorageInterface`, with a default SQL implementation; the
scheduler depends only on that contract and the queue transport. Journal/claim
persistence still requires coordinated backend work before an all-Redis attempt
backend can be supported. Atomic five-minute dispatch reservations
suppress duplicates and allow lost/enqueue-failed messages to be delivered again.
Messages contain only attempt ID/version; the queue worker invokes the existing
executor-aware runner. Waiting commits release the reservation for the next due
iteration. Pre-claim rejections are reconsidered after the reservation expires;
failed/expired-running work is never automatically replayed. Scans prioritize work
least recently dispatched. Kernel coverage includes delayed continuation, duplicates,
lost delivery, competing dispatch, blocked executors, fairness, safe logging, schema
upgrade and rejection of dispatch within an open transaction. Automatic submission
on task save, Messenger deployment, workspace ownership and retry/reset remain open.

## P5 — Templates, providers and derivative items

Deliverables:

- Named job checklist templates, static inclusion and decision-driven expansion.
- Provider assembly/alteration, nested scopes, collision-free names and dependency
  rewriting. Avoid arbitrary string substitution that corrupts condition syntax.
- Virtual items before interaction; persistence and stable identity once work begins.
- Orphan reconciliation and restoration when branches stop/restart producing items.
- Refresh/diff results for added, removed and changed items; recursion limits and
  visible configuration errors when expansion cannot safely continue.

Acceptance: repeated expansion is idempotent; grandchildren work; completed state
and outcomes are not overwritten; interacted-with work is never silently deleted;
new required items block completion immediately; unrelated branch changes do not
reset existing work; invalid/missing templates cannot silently complete the task.

## P6 — Resource workspace and interaction parity

Initial item reader implemented: `checklist.item_reader` returns authorized item
snapshots and visible-item lists without executing work. Optional handler progress
uses `ActionStateChecklistItemHandlerInterface` and `ChecklistActionState`. Dedicated
entity access operations inherit host/field permissions and support item visibility
denials without granting full entity-view access. The action-operation dispatcher
uses these checks too. Tests cover read-only viewers, hidden items, denied field
access, missing contexts, safe progress, no execution/persistence and unsaved hosts.
HTTP routes, UI integration, resources, attempts/ownership and cache aggregation
remain open; read snapshots must not be cached across users or changes.

Deliverables:

- Generic split checklist/action and resource-pane layout in reusable modules.
- Job/default and item resources, shared keys, contextual mappings, focus/refresh
  commands and access/cache metadata. No phone/chat integration dependency.
- AJAX updates for newly added/removed/blocked items and refreshed resources.
- Usable forms/API descriptions for decisions, stateful operations and waiting work;
  keyboard/focus behavior and a layout that remains usable on narrow screens.

Acceptance: browser tests exercise a decision that expands the checklist, updates
resources and completes generated work without reloading. Resources cannot disclose
entities the acting user cannot access. Shared resources refresh consistently, and
API invocation produces the same state/outcomes as the interactive path.

## P7 — Durable dispatch, user identity and parallel workers

Deliverables:

- Generic execution/dispatch interface and optional Messenger adapter. Messages
  identify item/attempt IDs; handlers reload current data rather than serialize
  long-lived entity snapshots.
- Reliable after-commit dispatch (outbox or equivalent), task-save evaluation
  scheduling and coalescing without lost wakeups or recursive save/dispatch loops.
- Atomic item claims, lease renewal/expiry, processing versus edit locks, stale
  message rejection and per-resource serialization for conflicting work.
- Explicit initiator/executor policy, account-switching with `finally` restoration,
  permission rechecks and per-message context cleanup. Define reassignment behavior.
- Delayed continuations, bounded retries, failure visibility and cancellation.
  Separate transport redelivery from a user-requested new execution attempt.
- Worker deployment guide: same image for web/CLI, shared storage, SQL transport
  starting point, supervisor/restarts, graceful termination and queue monitoring.
  Separate long-AI and short-action pools. Document optional cron-launched workers.
- Incremental Queue API interception, with exactly one consumer route per queue.

Acceptance: two independent items demonstrably overlap in execution; two workers
cannot claim the same attempt; conflicting items serialize; completion unlocks
successors; termination/redelivery do not blindly repeat side effects; cancelled or
superseded attempts cannot apply late results. Test commit/dispatch crash windows,
user deactivation and permission changes, account restoration after exceptions,
reassignment, saturated worker pools and task saves during processing.

A promise-like facade may be convenient for callers, but persisted attempts and
workers own execution. In-memory promises are not the persistence mechanism.

## P8 — Drupal AI and task-owned execution

Deliverables:

- Use Drupal AI providers behind durable run/session APIs; evaluate AI Agents and
  Tool API adapters before deciding how much agent-loop code to retain.
- Preserve task-owned assistant sessions and named item/component sessions, request
  identity, context-derived tools with per-run narrowing, messages and usage links.
- Short bounded-wait facade: dispatch asynchronously, check persisted completion
  briefly, return result or run ID. Always-running workers are needed for low latency.
- Pending AI run IDs in checklist state; background rechecks and safe application
  of results to outcomes. Decide browser-closed behavior for interactive items.
- Terminal-state guards, expiry, cancellation and duplicate callback/tool-call
  handling. Document limits around replaying tool results and external effects.

Acceptance: a provider call exceeding FPM's timeout completes in a CLI worker;
a short call returns inside the wait budget when capacity permits; another request
resumes the same run/session; a further user turn retains appropriate history;
closing the browser follows the chosen policy. Test worker failure, delayed/duplicate
callbacks, cancellation, identity/tool restrictions, usage attribution and state
retention on failure. Verify provider timeouts, not just queue configuration.

The separate Node runner is not required by the target design, but replacing it is
conditional on these proofs. Do not claim that queueing or agent serialization alone
provides durable exactly-once orchestration.

## P9 — Release the reusable framework

Deliverables:

- Independent Drupal consumer tests for package dependency combinations, fresh
  installation and upgrades from the existing Common foundation.
- A small reference workflow demonstrating nested services, draft gating, task
  dependencies, decision-generated items, resources, API operations and background
  execution. AI is an optional additional scenario.
- Developer guide covering plugin authoring, expected outcomes, conditions, state,
  execution identity, safe parallelism and resource rendering; operator guide for
  workers, diagnostics and retry/reset.
- Migration/deprecation notes for moved APIs, changed service state and any field
  name changes. Update Composer constraints and split verification as needed.
- Explicit supported-version matrix and release notes separating complete features
  from remaining limitations. Distribution remains GitHub unless separately decided.

Exit criteria: the reference workflow runs without Christian Jobs or CounselKit
business modules; package outputs match Common; migration tests pass; operational
limits are documented. Application teams can then choose their own integration.

## Tracking delivery

Keep this plan current as implementation PRs land. For each phase, record its PRs,
validated package versions, test evidence and unresolved limitations. A phase is
complete only when its acceptance criteria pass, not when its interfaces exist.
No phases above are marked complete by the initial task-port tests.
