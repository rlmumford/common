# Checklist

Programmable checklists for Drupal 10/11. Common is the source repository; the
`rlmumford/checklist` repository is its split package.

## Authoring job checklists

The existing Task Job editor embeds handler configuration forms. Decision items
configure their question, named choices, labels, reason requirements and optional
availability conditions. Each item also exposes applicability, actionability and
requiredness as native Drupal condition plugins, including condition strings,
Views conditions, constants and nested groups. XOR and XAnd take two operands.

Use the Update buttons after changing a plugin selection, and Add choice or Add
operand to grow a section. These buttons rebuild the configuration form without
saving the job or executing checklist items. Add/Update returns to the job draft;
Save commits it and Cancel discards the draft. Existing settings outside the
editor's controls are preserved.

Available contexts include the host definition and expected outcomes from the
job's current draft, so later items can map earlier outcomes before any task
exists. Configuration forms do not fetch their runtime values. The job exports
the modules and configuration entities required by its handlers and conditions.

Enable `checklist_entity_template_ui` for template creation/application authoring
and embedded or reusable Flexiform editors. See the integration's README for
details; its UI dependencies are optional at runtime.

## Checklist HTTP API

Enable the optional `checklist_api` module to expose checklist item state and
action operations over JSON. The base `checklist` module provides the reader,
operation dispatcher and shared access checks without adding HTTP routes.

The routes address a host entity and one checklist field value:

```text
GET|HEAD /checklist/{entity_type}/{entity_id}/{field_name[:delta]}/{item_name}
GET|HEAD /checklist/{entity_type}/{entity_id}/{field_name[:delta]}/{item_name}/operations
POST     /checklist/{entity_type}/{entity_id}/{field_name[:delta]}/{item_name}/operation
```

The first route returns the authorized item snapshot, including optional action
progress. The operations route lists actions currently available to the caller;
discovery is a snapshot and never grants permission to execute. The POST body
contains the selected operation and its parameters:

```json
{
  "operation": "choose",
  "parameters": {
    "choice": "approve",
    "reason": "The evidence is complete."
  }
}
```

Execution resolves the current host and checklist again, then rechecks host,
field, item and operation access and readiness. Requests use the site's configured
authentication. The API module does not require Drupal REST; callers that need
another transport can use the shared checklist services directly.

## Checklist action resources

Handlers can implement `ActionResourceChecklistItemHandlerInterface` and return a
`ChecklistActionResource` from `getActionResource()`. The resource contains a
shared key, render-array content, optional label, ordering weight, and display
metadata. Keep content as a render array so Drupal can apply access checks and
cache metadata from nested elements.

The interactive formatter collects resources only for items visible under the
`view action state` access operation and prepares the item's current contexts
before checking its applicability and actionability. Completed and failed items
may keep contributing a resource for review or recovery. Items that use the same
key share one pane; every owning item name is retained and the last eligible item
in checklist order supplies the current content. The formatter renders the
resources in an accessible right-side pane and supports selecting a resource
from either its checklist row or the pane navigation. Resource refresh after
AJAX actions and job-level default resources are separate integration work.

## Outcomes and contexts

Handlers implement `ExpectedOutcomeChecklistItemHandlerInterface` to declare
named typed-data outcomes. Items store values through `setOutcome($name, $value)`;
save the item to persist them. Outcomes remain on checklist items. Persisted list
outcomes require the Typed Data Plus list-decoding fix in
[MR !5](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/5).

`checklist.context_collector` provides configuration and runtime contexts named
`item:<item name>:<outcome name>`, plus `checklist:entity`. Configuration collection
exposes definitions before outcomes exist, preserving complex properties, lists
and entity constraints. Runtime collection supplies the typed outcomes, including
known values that are still absent, with the item cache tags.

Context-aware handlers use ordinary Drupal `context_mapping` configuration:

```yaml
context_mapping:
  value: 'item:source:value|upper'
  owner: 'checklist:entity'
  current_user: '@user.current_user_context:current_user'
```

`checklist.context_preparer->prepare($checklist, $item)` replaces the handler's
context slots and maps current values through Typed Data Plus. Property paths,
filters and qualified global providers use its context handler. The method returns
`FALSE` when required values are unavailable; malformed mappings and incompatible
types throw configuration exceptions. Optional missing values clear previous
values. Collection does not cache outcome values across preparations.

`Checklist::process()` prepares each unfinished item before evaluating it, so
later items see earlier outcomes from the same pass. `isCompletable()` also
prepares contexts: unavailable required values prevent completion, because the
item's applicability and requiredness cannot yet be evaluated safely. Completed
and explicitly not-applicable items do not need execution contexts.

This preparation does not authorize actions or switch users. Callers remain
responsible for access and execution identity. Global provider contexts retain
Drupal's request-level caching; account switching and provider cleanup in long-lived
workers remain part of the planned execution layer. Direct calls to handler action
methods must prepare contexts themselves. Unified form/API/action dispatch,
intermediate state, attempts and operational history remain planned.

## Created entity outcomes

The `create_entity:<entity type>` handler publishes the saved entity as an outcome
named after its entity type, on both automatic and interactive completion. Later
items can map `item:<creator item>:<entity type>` or a property selector beneath it.
The outcome is available in the same processing pass and after storage reload.

Automatic creation uses the configured bundle. A handler configured for user
selection requires an explicit bundle before creating an entity. Both action
paths use `completeCreation()` to save the entity, record its outcome, and then
complete/save the item. The supplied entity must match the handler's entity type
and fixed bundle. Existing form validation and caller access responsibilities
still apply; this helper does not provide worker claims or replay protection.

## Completion readiness

Applicability has three values: `TRUE` means applicable, `FALSE` means definitely
not applicable, and `NULL` means not yet known. Unknown applicability prevents
execution and completion, even for an optional item whose applicability is still
undetermined. Completed items and explicitly not-applicable statuses keep their
existing behavior.

Processing checks completion against current item readiness after running actions,
using the same `isCompletable()` guard as explicit completion. Optional unfinished
manual work does not block completion. Applicable required work blocks until it
is complete, including when it is unactionable or has failed. Changes made by a
later action are considered when rechecking earlier items; newly applicable earlier
work receives its execution turn on the next processing pass.

## Condition gates

Handler configuration accepts ordinary Drupal condition plugin configurations.
`actionability` determines whether an applicable item can run; dependencies on
other items are one use of that gate:

```yaml
conditions:
  applicability:
    id: user_role
    roles: [authenticated]
    context_mapping:
      user: '@user.current_user_context:current_user'
  required:
    id: 'condition_constant:true'
  actionability:
    id: condition_and
    conditions:
      - id: condition_string
        condition_string: "items.source.status == 'complete'"
      - id: condition_string
        condition_string: "items.source.outcomes.value|upper == 'APPROVED'"
```

Omitted gates default to TRUE. Missing required condition contexts yield unknown
applicability, blocked actionability or conservative requiredness. Condition strings
can explicitly test optional missing outcomes with `exists`/`empty`; malformed
configuration raises an error. Native condition plugins retain their own required
context definitions, mappings and negation semantics. Handlers overriding the base
readiness methods must call the parent implementation to retain configured gates.

The collector exposes `items.<name>.status` and `items.<name>.outcomes.<outcome>`
as a typed tree at configuration time and runtime. Condition evaluation also aliases
`checklist:entity` to `checklist` for property traversal. Each evaluation uses fresh
plugin instances and current outcomes, including those produced earlier in the same
processing pass. Qualified global providers retain their request-level behavior.

Item actions and unfinished-item form submissions recheck the gates. These checks
do not add authorization, worker claims or cross-request concurrency protection.
Direct handler calls remain internal and require caller preparation and gating.
Interactive checklist output is uncacheable until gate cache metadata is aggregated.
Configuration is currently programmatic/exported; a condition selection UI is not
included. This integration requires the Typed Data Plus condition discovery and
missing-context fixes in [MR !6](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/6).

The base handler receives `checklist.condition_evaluator` through Drupal's
`ContainerFactoryPluginInterface::create()` factory. Handlers with custom factories
and constructors must pass that service to the base constructor.

## Decisions

The `decision` handler presents named choices. Each option can require a reason
and have an `available` condition using the same Drupal condition plugins as item
gates. Configuration is currently exported/programmatic:

```yaml
question: 'Approve this work?'
presentation: buttons
options:
  approve:
    label: 'Approve'
    require_reason: true
    available:
      id: condition_string
      condition_string: "items.review.status == 'complete'"
  decline:
    label: 'Decline'
```

Decisions default to one submit button per available choice, labelled with the
option's human-readable label. Each button submits its machine name through the
existing AJAX completion path. Set `presentation: radios` or `presentation: select`
for a selector followed by a Choose button. Reasons are entered before submitting;
only choices configured with `require_reason` demand a non-empty reason. All
presentations recheck availability and use the same validation.

Forms and `choose($choice, $reason)` share validation and persistence. A successful
choice writes `decision` as a labelled string enum and `reason` as free text,
completes the item and
saves it once. Later items can map `item:review_decision:decision` or test
`items.review_decision.outcomes.decision`. Expected definitions exist before a
choice is made. These are interactive decisions; `action()` does not guess a
choice, and automatic processing leaves them for a caller.

`ActionOperationsChecklistItemHandlerInterface` introduces operation discovery and
execution for tool/API adapters. `actionOperations()` describes `choose` with a
JSON Schema containing the currently available choice names and conditional reason
requirements. `executeActionOperation('choose', ['choice' => 'approve', 'reason' =>
'Reviewed'])` returns the saved `decision`/`reason` values. The implementation
validates payloads itself; adapters must not treat discovery as authorization.

Every submission checks the containing entity's update access, incomplete item
status, applicability, actionability and the selected option's current availability.
Unavailable or unknown choices, missing reasons and malformed operation parameters
leave outcomes and completion unchanged. Completed/failed items cannot be chosen
again through this handler. Discovery returns no operations when access or item
gates prevent a choice. Invalid configuration remains an error.

HTTP routes, authentication adapters, AI selection, execution identity switching,
concurrent submission claims, attempt history and decision-generated checklist
items remain planned. This API uses the current Drupal account and the loaded
checklist; it does not reload stale copies or make concurrent submissions safe.

The decision outcome uses Typed Data Plus's `StringEnumDefinition`. Its machine
value is still stored and compared as a string; `getValueLabel()` exposes the
display label and `getPossibleOptions()` exposes all configured choices, including
options that are unavailable now. The persisted item already contains a snapshot
of its handler configuration, including names and labels. The handler reconstructs
the enum from that configuration on reload; no definition dump is stored with the
outcome. Changing job/template defaults does not rewrite persisted item options.
String context mappings and condition comparisons continue to use machine values.

Labelled decision outcomes require [Typed Data Plus !7](https://git.drupalcode.org/project/typed_data_plus/-/merge_requests/7).

Changes to the item's own configuration or to plugin code can still change the
reconstructed definition. Typed references retain readable stored values even when
current constraints reject them; explicit validation reports the violations.
Versioning checklist plugin implementations is deferred. Plugin changes must
preserve compatibility with existing outcome definitions in the meantime.

## Operation dispatch

API and tool adapters use `checklist.action_operation_dispatcher` for all item handlers
implementing `ActionOperationsChecklistItemHandlerInterface`:

```php
$dispatcher = \Drupal::service('checklist.action_operation_dispatcher');
$operations = $dispatcher->discover($checklist, 'review_decision');
$result = $dispatcher->execute($checklist, 'review_decision', 'choose', [
  'choice' => 'approve',
  'reason' => 'Reviewed',
]);
```

Both methods check the containing entity's update access for the current account,
prepare fresh handler contexts from the supplied checklist, and require incomplete
status, known-true applicability and actionability. Missing required contexts defer
execution; invalid mappings or condition configurations remain errors. Completed,
failed and not-applicable items cannot execute normal operations; this is not a
retry/reset API. A handler without the operations interface exposes no operations.

Discovery returns an empty array for inaccessible, blocked or unsupported work.
Execution throws `AccessDeniedHttpException` for denied host access,
`DomainException` for blocked/unsupported work and `InvalidArgumentException` for
unknown item names or operations not currently advertised by the handler. Execution
repeats preparation and discovery, so an earlier operation list is never permission
to invoke an operation that has since become unavailable. Handler validation errors
and execution exceptions propagate to the adapter.

Handlers own input validation, operation-specific access, domain effects and
persistence. The dispatcher neither saves again nor completes items automatically.
Parameter schemas describe the API; each handler must enforce its inputs. Use the
same domain method from forms and operations, as the decision handler does.

Adapters establish the current account and perform transport authentication and
CSRF checks. Supply the current checklist, including any current in-memory outcomes;
the dispatcher does not reload persisted entities or merge form tempstore changes.
It uses no privileged-user fallback and performs no account switching. Discovery
results are request-local snapshots; do not cache them across users or changes.
Task-level execution policies, durable claims, cross-request stale-write protection
and HTTP/AI adapters remain separate implementation work.

## Checklist resolution

`checklist.resolver` accepts the entity supplied by the caller, including an unsaved
entity or current form-state edits:

```php
$checklist = \Drupal::service('checklist.resolver')->resolve(
  $entity, 'checklist', 0, 'update'
);
```

Use the actual checklist field name on the host. `view` is the default operation;
`update` additionally requires host update and field edit access. Both require host
and field view access for saved hosts. Unsaved hosts require Drupal create access
instead of host view/update access; field view/edit checks still apply. Invalid
fields/deltas, empty field items, non-checklist fields and checklist types intended
for another host type raise `NotFoundHttpException`; denied access raises
`AccessDeniedHttpException`. Malformed plugin configuration remains an error.

The resolver preserves the supplied entity and its attached checklist, including
unsaved item state. It does not reload the host, clear entity caches, change the
translation/revision, save anything or fetch another tempstore snapshot. A caller
that needs current persisted data must load it before resolving. Existing form
routes retain their current tempstore behavior. Persisted item loading scopes queries
to checklist type as well as host ID and field/delta key, isolating host types with
matching IDs.

The field adapter's `getLocalValue()` reuses the attached checklist or constructs
one on that entity. Workspace adapters restoring a separately serialized checklist
must attach it to the entity's computed `checklist` property; entity serialization
does not preserve computed field values. An attached checklist whose host is a
different entity instance is rejected: supply its actual host so access checks and
handler contexts operate on the same object. Shared workspace composition, ownership
and version checks remain planned. Resolving an unsaved host does not imply its
handlers can persist item results before the host has been saved.

Delta addresses are locations and must not be stored as durable tool identities
until the identity policy in the
[interaction contract](https://github.com/rlmumford/common/blob/2.x/docs/CHECKLIST_INTERACTION_CONTRACT.md)
is implemented. HTTP adapters must define their revision/translation targeting;
the resolver makes no implicit choice on their behalf.

The existing checklist tempstore is scoped by host entity type and keyed by host
UUID plus checklist field/delta key. It can hold an unsaved host/checklist across
requests; saving the host does not change that key. Separate unsaved hosts have
separate UUIDs. After saving, explicitly update the stored graph: the unchanged key
does not rewrite its serialized unsaved host snapshot. Tempstore expiration still
applies; this is not a replacement for durable attempt/state storage.

## Item reads and action progress

`checklist.item_reader` provides transport-independent read snapshots:

```php
$reader = \Drupal::service('checklist.item_reader');
$items = $reader->readItems($entity, 'checklist', 0);
$item = $reader->read($entity, 'checklist', 0, 'review');
```

Both use the entity-based resolver and preserve the supplied workspace. Item reads
contain `name`, `title`, durable `status`, `contexts_available`, `applicable`,
`required`, `actionable` and optional `action_state`. They do not call actions,
process/complete the checklist, save entities or acquire ownership. `actionable`
describes workflow readiness, not permission to execute. Missing required runtime
contexts suppress plugin progress, make applicability unknown, and conservatively
report required/non-actionable work. Invalid configuration remains an error.

Handlers can implement `ActionStateChecklistItemHandlerInterface::getActionState()`
to return a `ChecklistActionState` or NULL. The progress object allows only stage,
plain-text message, completed/total counts, underlying update timestamp and an
input-required flag. Unavailable counts/timestamps remain NULL; polling must not
invent activity timestamps or percentages. Progress providers read stored state;
they must not poll external services, execute work or expose raw state/prompts/error
payloads. Plugins remain responsible for the suitability of their text for the
current viewer. Adapters must escape text when rendering HTML.

The reader checks the dedicated Drupal entity access operation `view action state`
before preparing contexts or invoking the progress provider. Item lists omit hidden
items; direct reads return the same not-found error for hidden and unknown names.
Host/field access denial is reported by the resolver. The action-operation dispatcher
also requires `view action state` and `execute action operation`. These inherit host
view/update and checklist field view/edit access (create access for unsaved hosts).
Modules can restrict items using `hook_checklist_item_access()` or
`hook_entity_access()` for those operation names. An item grant cannot override a
host/field denial. This does not grant ordinary entity `view` access or expose the
whole item through another serializer.

Snapshots are request-local. HTTP adapters must disable caching until cache metadata
is aggregated across access, contexts, conditions and progress providers. Do not
cache across users or changes. No HTTP routes or existing UI replacement ship in
this slice. Outcomes, resources, attempt identity, workspace ownership/version and
safe blocked-reason descriptions remain later read-model additions. The automatic
processor continues to inspect the full checklist; visibility is not applicability.

## Intermediate working state

Handlers opt into item-scoped working state with
`StatefulChecklistItemHandlerInterface::stateDefinitions()`. Return named Drupal
typed-data definitions reconstructed from the saved handler configuration. State
uses its own internal `state` typed-reference field; definitions are not copied into
every stored value. Outcomes continue to be the published results for later items.

```php
$item->setWorkingState('run_id', $run_id);
$item->save();
$run_id = $item->get('state')->get('run_id')->getValue();
```

`setWorkingState()` accepts only declared names on incomplete items and does not
save automatically. Scalar, list, map and entity values use the existing Typed Data
Plus reference storage. As with outcomes, typed-data validation is explicit; this
internal setter is not an input-validation or authorization boundary.

Failure retains state across reloads. `setComplete()` clears state in memory,
including for an unsaved item held in tempstore. Item storage also clears state on
completion after all presave hooks, covering direct status writes. Cleanup removes
backing rows and clears retained typed property objects while preserving outcomes.
`clearWorkingState()` is an internal primitive, not a public reset or retry action.

Raw state is internal and denied through normal field view/edit access, including
for administrators. It is not added to item outcome contexts or the `items` typed
context tree. Trusted handlers can read it directly and expose an appropriate
`ChecklistActionState` projection to authorized viewers. This is access separation,
not encryption; raw state may contain sensitive data and needs a retention policy.

Run database updates on existing installations: `checklist_update_10001()` installs
the new field while retaining existing item data/outcomes. Fresh installs include
the field automatically. Kernel coverage includes a pre-state-schema upgrade.

This slice supplies the state model and lifecycle cleanup. Versioned attempts,
operational history, ownership/claims and late-result rejection remain required
before external concurrent writes or user-facing resume/start-fresh/takeover actions.
There is no implicit per-viewer state fork and no automatic state dump into outcomes.

## Attempt journal

`checklist.attempt_journal` is an internal persistence service for durable attempt
metadata and append-only transition events. It is not yet an execution coordinator:
existing forms, operations and automatic processing do not call it automatically.
No HTTP route or public retry/reset action is introduced.

Each attempt has its own UUID and retains the item UUID, predecessor, initial /
resume / fresh intent, entry path (action, action form or action operation), optional
operation name, initiating and intended executing user IDs, timestamps, status and
version. User IDs record attribution; they do not switch accounts or grant access.
The item UUID works before saving the item or host, provided the workspace retains
that same item instance/UUID. Rebuilding an unsaved item with a new UUID creates a
new stream. Saving an item does not change its UUID. Raw state, operation parameters,
provider payloads and exception dumps are not copied into the journal.

```php
use Drupal\checklist\Attempt\ChecklistAttempt;

$attempt = $journal->create(
  $item, $initiator_id, $executor_id,
  ChecklistAttempt::ACTION_OPERATION, 'choose',
);
$running = $journal->transition($attempt, ChecklistAttempt::RUNNING, $executor_id);
$failed = $journal->transition($running, ChecklistAttempt::FAILED, $executor_id, 'Provider unavailable');
$resume = $journal->create(
  $item, $initiator_id, $executor_id,
  ChecklistAttempt::ACTION_OPERATION, 'choose',
  mode: ChecklistAttempt::RESUME,
  previous: $failed->id,
);
```

The example records history only; it does not execute `choose`, mark the item
failed, reopen it or modify working state. The execution coordinator must apply
item changes and attempt transitions together, enforce current access and ownership,
and check current gates before invoking a handler. A fresh-intent record is not
permission to repeat an external effect. Retained-state resume and fresh-state reset
remain coordinator work; this service does not implement either state mutation.

| Current attempt status | Allowed next statuses |
| --- | --- |
| queued | running, cancelled, superseded |
| running | waiting, succeeded, failed, cancelled, superseded |
| waiting | running, failed, cancelled, superseded |
| succeeded, failed, cancelled, superseded | None; terminal records are immutable. |

Only one nonterminal attempt exists per item through this API. A successor must
name the exact latest predecessor. Resume requires a failed predecessor; fresh
permits failed, cancelled or superseded. Success is not implicitly reopened.
Cancelled/superseded records preserve history without implying external work has
stopped. Waiting-to-running continues the same attempt; resuming after failure
creates a new one.

Each transition compares the expected version in the database and atomically
appends an event containing old/new status, actor, timestamp and optional safe
reason. Stale or duplicate submissions throw `ChecklistAttemptConflictException`;
invalid transitions throw `DomainException`. This fences journal writes only; it
is not a worker claim, lease, idempotency key or fence on entity/provider writes.
No database transaction remains open across provider calls.

`load()`, `latest()` and `history()` are internal reads without access checks.
Adapters must resolve and authorize the item/host before exposing any projection,
including historical reasons. History is bounded to 1–100 events per call, ordered
by version, with an exclusive `after_version` cursor. Follow predecessor IDs to
inspect earlier attempts rather than loading an unbounded item history.

Run database updates: `checklist_update_10002()` adds the three journal tables.
Existing items retain their state/outcomes and receive no fabricated past attempts.
Records are retained independently of item deletion or tempstore expiry; deployment
retention/purge policy and access-filtered history UI are follow-up work. Unsaved
work may leave orphaned attempt metadata if its workspace expires; the journal does
not preserve the host graph or replace durable working-state storage.

## Worker iteration claims

`checklist.attempt_claims` coordinates iterations within an attempt. A worker claims
queued work, performs a bounded unit of work, then commits either `waiting` with a
continuation delay or a terminal status. Waiting-to-running keeps the attempt UUID;
a retry after failure still creates a successor attempt. This supports polling an
existing provider run and processing successive batches without starting the work
again on every request.

```php
$claim = $claims->claim($attempt, lease_seconds: 300);
// Run the bounded handler/provider step here, outside a database transaction.
$waiting = $claims->commit(
  $claim,
  ChecklistAttempt::WAITING,
  apply: function () use ($storage, $item_id, $cursor) {
    // Recheck access and relevant gates when applying the result.
    $item = $storage->loadUnchanged($item_id);
    $item->setWorkingState('cursor', $cursor)->save();
  },
  delay: 15,
);
```

The callback runs inside a short transaction after reserving the live claim. It
must only revalidate and apply local writes on the same database connection.
Provider calls, handler execution, messages and other external effects belong
outside it. A callback exception rolls back the item writes, attempt transition,
history event, claim release and due time together. Discard mutated PHP objects and
reload after rollback; database rollback cannot restore those objects or undo
external effects. The service also rejects expiry during the callback.

| Operation | Behaviour |
| --- | --- |
| `claim($attempt, $lease_seconds)` | Atomically records running and grants a token for due queued/waiting work. |
| `renew($claim, $lease_seconds)` | Extends a live lease and replaces its token; retain the returned handle. |
| `commit($claim, $status, ...)` | Applies local results and releases the claim atomically. Waiting permits a delay; terminal statuses do not. |
| `expire($attempt, $actor)` | Marks a matching expired running claim failed, retaining working state and recording the supervisor. |
| `due($limit)` | Returns at most 100 queued/waiting attempt IDs whose delay has elapsed; this read grants no execution authority. |

Claim tokens and journal versions are checked in conditional database writes.
Replayed handles, old handles after renewal, and late writes after expiry are
rejected before result application. Tokens are internal bearer credentials and
must not be exposed in the item reader, API responses, logs or AI prompts. The
expiry carried by a PHP handle is informational; the stored expiry is authoritative.
Heartbeat renewal does not create another attempt or append a status event.

Claim duration defaults to five minutes and can be 1–86,400 seconds. Workers should
choose a duration that covers one bounded iteration and renew before expiry when
needed. A blocking provider call cannot heartbeat on the same PHP thread; use a
suitable lease or a provider request/poll pattern. Claim methods reject an existing
outer transaction so a caller cannot keep a grant uncommitted across external work.

Expired running work is excluded from `due()` and is never automatically reclaimed.
The supervisor explicitly records expiry and reconciles possible external effects
before authorizing a retry. Merely expiring a claim changes attempt history, not
the item's disposition or state. No state dump is copied to outcomes or history.
Direct journal transitions cannot bypass an active claim or start waiting work
before its due time. Legacy unclaimed running attempts remain unclaimed on upgrade;
there is no invented lease or automatic recovery.

This is the worker coordination primitive. The automatic runner below uses it;
the Queue API scheduler is described below. Other callers establish the executor account,
enforce current access and gates,
select an authoritative saved item or unsaved workspace, and apply the appropriate
item disposition/state/outcome changes. `due()` has no access/path filtering; a
scheduler must select its supported execution paths and authorize each target.
Workspace editing ownership and worker claims are separate. Existing forms,
operations and automatic processing are not yet wired to claims, so out-of-band
entity saves and external effects are not fenced by this service. Messenger and
shared workspace integration remain follow-up work. The
automatic runner below supplies iteration results and identity restoration for
its supported bindings.

Run database updates: `checklist_update_10003()` adds claim/expiry/due columns and a
due-work index, preserving existing attempt metadata and transition history.

## Individual item execution

`checklist.item_executor::run($attempt, $lease_seconds = 300)` executes one
bounded iteration of **one item**, for an explicitly authorized automatic attempt. A scheduler can
call it again when a waiting attempt becomes due. The attempt UUID stays the same
across these calls; each call obtains its own worker claim.

Automatic handlers opt in through `IterativeChecklistItemHandlerInterface` and
implement `actionIteration(ChecklistAttempt $attempt): ChecklistItemResult`.
The attempt snapshot supplies a stable ID for provider idempotency; it contains
no claim token. They read their prepared
contexts and stored working state, perform one bounded unit of provider/batch work,
and return named state/outcome changes rather than saving the item themselves:

```php
return new ChecklistItemResult(
  ChecklistAttempt::WAITING,
  state: ['run_id' => $run_id, 'cursor' => $next_cursor],
  delay: 10,
);
```

Waiting preserves incomplete item status and working state. Success publishes the
returned outcomes, completes the item and clears working state. The normal checklist
completion rules still govern resolving the containing task; the runner does not
complete the host checklist automatically. Explicit failure
can return diagnostic working values, which are retained while the item and attempt
are marked failed. Omitted state/outcome names are preserved; every supplied name
must be declared by the handler and its value must pass typed-data validation.
An unexpected handler exception retains previously committed state, records a generic
failure reason and marks the item failed if result-time checks still pass, then
rethrows the original exception. Raw exception messages are not copied into history.

The runner reloads the attempt's executor and switches to that active user before
checking host/field/item access and preparing contexts. It restores the caller even
on exceptions. The new `execute iteration` item access operation inherits host
view/update and checklist-field view/edit restrictions, independently of viewer
visibility. Providers and handlers therefore see the executor, not the cron user.
Initial submission must separately authorize the chosen executor and target; this
internal service is not a public impersonation API.

Provider work runs outside a database transaction. Before saving results, the
runner reloads the account, host, executing item and sibling outcome sources,
rechecks permissions and native applicability/actionability gates, and compares
item/host data and mapped context values with the inputs used for execution.
Changed inputs or revoked access reject the result. A still-live rejected claim is
closed with a safe failure record, leaving item data unchanged; expired claims are
left for explicit supervisor reconciliation. Result application, item writes and
history share the claim transaction. Discard entity objects after rollback.
Mapped contexts must contain comparable scalar/array/typed/entity values; arbitrary
service/resource objects are rejected. Host comparison is deliberately conservative:
even unrelated host edits can reject an in-flight result.

This first runner is for **persisted autonomous items** on single-value,
untranslatable, non-revisionable checklist fields. Revisionable hosts, including
tasks, are supported when their checklist field is shared across revisions. The
runner reloads the host's current default revision for context/access checks;
host changes still invalidate an in-flight input snapshot. It accepts initial
`action` attempts only; it does not perform resume/fresh resets. Interactive/form
and action-operation handlers are excluded until workspace ownership is integrated.
Unsaved workspaces, multivalue checklist identity, translated/revision-specific
bindings and retry authorization need explicit adapters. These restrictions are
checked before handler invocation, rather than inferring a draft or delta binding.
The UUID-based journal/claims themselves still support unsaved item identities.

The checklist processor starts saved result-based items through the caller-authorized
item executor below, and the item's `action()` method refuses direct execution. Existing non-iterative handlers
continue through the previous path. Plugins must not save or mutate the item during
`actionIteration()`; return changes for claim-protected application instead. Claims
cannot undo external effects or fence arbitrary programmatic entity writes outside
this protocol. Use provider idempotency and reconcile uncertain external effects;
expired work is never automatically rerun.

There is no automatic attempt creation on task save or public retry/takeover route.
Calling `process()` runs supported short items inline. The Queue API adapter
invokes the same item executor for deferred work and yielded continuations.
Blocking calls must fit within the supplied lease; this runner does not heartbeat
a blocked PHP thread.


## Checklist processing and inline item execution

`Checklist::process()` delegates to `checklist.processor`, a `ChecklistProcessor`
that operates on the whole checklist. `checklist.item_executor` operates on one
item. `checklist.item_iteration_scheduler` selects individual due attempts for the
`checklist_item_iteration` queue. These are separate scopes; an item iteration is
not a pass over the checklist.

The processor runs ready short work inline, refreshes the checklist's item/context
graph after results, and revisits blocked items while progress is being made. An
entire checklist can finish in one request, including a dependent item listed before
the item that produces its required outcome. Each item runs at most once per call;
there is no tight polling loop for waiting items. Completion uses the refreshed graph.

Use `checklist.processor::process($checklist, $budget_seconds = 10)` to choose the
inline budget. Zero records ready result-based work for workers without executing
it. Once the budget expires, remaining ready result-based items are deferred. The
budget controls starting another item, not interrupting a running function. Handlers
that can block for a long time implement `BackgroundChecklistItemHandlerInterface`
to require a worker from the outset. Existing legacy synchronous handlers retain
their action contract and are left for a later call if the budget is exhausted;
they are not silently placed on the result-based worker path.

`checklist.item_executor::submit($item, $defer = FALSE)` is the shared starting point:

- Reload and authorize the saved item as the current authenticated caller, recording
  that user as initiator and executor. No arbitrary executor ID is accepted.
- Prepare persisted contexts and gates once, record/claim the attempt, invoke the
  handler inline, and recheck fresh access/inputs when committing its result.
- Use the same private execution path from `run()` in a queue worker. Inline work
  needs no queue message, dispatch reservation or additional submission service.
- Return a waiting attempt when the handler yields; cron dispatches its continuation
  when due. Explicit deferral, background-only handlers and calls inside an outer
  transaction return a queued attempt instead of invoking a handler.
- Check current access before returning an existing attempt unchanged. Repeated or
  competing submissions never rebind its executor, retry a failure, or poll waiting
  work inline. Access/configuration errors propagate; temporary gates return NULL.

For a simple handler, extend `AutomaticChecklistItemHandlerBase` and implement
`actionIteration()` to return a `ChecklistItemResult`. Returning `SUCCEEDED` records
the audit, applies declared outcomes and completes the item in that request. There
is no required working-state definition: implement `StatefulChecklistItemHandlerInterface`
only when the item needs state across requests. The same result contract supports
waiting or failure, and the same attempt/claim rules protect both inline and worker
execution. Legacy action plugins are not automatically converted to this contract.

Save edits before submission: the passed item is an identity handle and stored
configuration governs execution. The existing saved autonomous-item restrictions
still apply. Generated items must be persisted by the consumer first; no blanket
entity-save hook, concurrent materialization, alternate executor policy or public
retry/takeover is introduced. Inside an outer transaction, journal submission rolls
back with the caller and provider execution is deferred until after commit.

The checklist processor can also be called from a PHP worker with a freshly loaded
checklist and explicitly established execution identity. A whole-checklist message
adapter still needs coalescing, identity/access policy and result/progress reads;
the current queue carries individual item attempts. Results are persisted, rather
than returned across threads into a live original PHP request.

## Queue and cron scheduling

`checklist.item_iteration_scheduler::dispatch($limit = 50)` sends due initial `action`
attempts to the `checklist_item_iteration` Queue API queue. `checklist_cron()` dispatches
one batch; Drupal cron then runs the queue worker with a 15-second queue budget.
Each message contains only the attempt UUID and expected journal version. Handlers,
entities, account objects, credentials, working state and outcomes are not serialized
into messages. Queue delivery grants no access: the runner reloads the executor,
checks permissions and gates, and claims the iteration before calling the handler.

The scheduler depends on `ChecklistAttemptDispatchStorageInterface`, supplied by
`checklist.attempt_dispatch_storage`. Its `reserveDue($limit)` operation selects
and atomically reserves a bounded batch, returning only attempt IDs and versions.
The scheduler has no database or clock dependency. The default
`DatabaseChecklistAttemptDispatchStorage` uses the attempt table as the durable
scheduling source. Replace the service to supply a different dispatch backend.

This is a dispatch-storage boundary, not a complete pluggable attempt backend.
Journal and execution claims still use SQL, and waiting commits clear the dispatch
reservation atomically with result writes. A Redis implementation must coordinate
with those operations; merely maintaining a separate Redis index would not preserve
the commit/version guarantees. Moving the whole attempt system needs corresponding
journal, claim and result-persistence integration.

The default storage preserves the existing selection policy: initial automatic
action attempts, queued/waiting, due, unclaimed and without a live dispatch
reservation. Ordering is dispatch expiry (zero first), due time, creation time,
then attempt UUID. This is not strict FIFO; newly yielded continuations reset their
reservation to zero. Selection does not evaluate permissions or checklist gates. Dispatch must run outside any
open database transaction, after authorized submission commits. A single conditional
update reserves a delivery for five minutes without changing the journal version or
adding a history event. Queue writes happen outside transactions. A failed enqueue,
a crash before enqueue, or a lost message is retried by a later scan after reservation
expiry. Delivery is at least once: duplicate or stale messages are harmless through
the runner's version/claim checks, but external effects still need idempotency.
Reservations reduce duplicate delivery; they do not guarantee one physical queue
message or replace execution claims or user workspace ownership.

Waiting results atomically clear the dispatch reservation and set the next due time.
The next scan can then enqueue the new version when that time arrives. Terminal
attempts and running attempts (including expired claims) are excluded. A pre-claim
access/gate rejection leaves the attempt queued or waiting and retains its delivery
reservation, so it is reconsidered after five minutes rather than retried in a tight
loop. A handler failure remains failed; uncertain expired executions require explicit
reconciliation. The worker acknowledges rejected messages and logs only safe attempt
identifiers, never raw provider exceptions. Scan order prioritizes never-dispatched
and least-recently-dispatched work so blocked items cannot monopolize every batch.

The scheduler does not create attempts, choose executors, reset failed work, or
resolve the containing task. Consumers must authorize initial submissions and use
the runner's supported saved autonomous bindings. Unsupported paths and successor
modes are not dispatched. Continue running the scheduler even when queue consumption
is moved to a separate worker: this recovers missed deliveries and schedules delayed
continuations. A five-second item delay means *eligible after five seconds*; actual
latency depends on the dispatch/worker cadence.

For CLI consumption, run `drush queue:run checklist_item_iteration` after dispatching due
work. Separate processes provide parallel execution; installing this code does not
provision or supervise them. Configure the Queue API backend through Drupal's queue
settings; database queue storage is the default. Use one consumer route per queue.
A dedicated Messenger adapter and deployment validation are still follow-up work.
Cron's 15-second budget controls starting further items; it cannot interrupt a single
blocking provider call. Each item iteration currently uses the executor's 300-second claim.
Provider timeouts must fit that lease and the PHP worker limit. Long calls should run
in CLI workers with appropriate limits, not web cron subject to PHP-FPM timeouts.

Run database updates: `checklist_update_10004()` adds `dispatch_expires`, defaulting
to zero so existing due attempts can be dispatched. It preserves all attempt versions,
working state and history, and does not invent delivery or execution events.
