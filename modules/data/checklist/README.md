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
