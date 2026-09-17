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
