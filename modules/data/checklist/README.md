# Checklist

Programmable checklists for Drupal 10/11. Common is the source repository; the
`rlmumford/checklist` repository is its split package.

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
