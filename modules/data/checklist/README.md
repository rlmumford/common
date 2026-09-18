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

Forms and `choose($choice, $reason)` share validation and persistence. A successful
choice writes the string outcomes `decision` and `reason`, completes the item and
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
