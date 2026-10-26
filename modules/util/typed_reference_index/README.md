# Typed reference index

This optional module provides reverse lookup for entity references nested in
`typed_data_reference` fields. It is independent of tasks, checklists and
documents. Ordinary entity-reference fields already have queryable SQL storage
and are not copied into this index.

`typed_reference_index.references` reads declared entity/list/map definitions
and saved scalar, dotted-path and serialized values. It stops at entity
boundaries, does not load referenced entities and does not interpret arbitrary
numbers or untyped maps as entity references.

`typed_reference_index.index` maintains current source type/ID, translation,
field, property path and target type/ID entries in `typed_reference_index`.
Insert/update hooks replace the default revision's entries in the SQL entity
save transaction. Source deletion removes them. No relationship history,
workflow status, task ownership or document revision is stored here.

`referringSources()` returns a composable SQL source-ID query. It is a low-level
lookup: consumers must check target access and apply source access and any
organization restrictions before producing rows or counts. Display labels and
business relationships must be obtained from the source fields, not the cache.

## Rebuilding

Installation schedules a bounded cron rebuild of existing SQL content entities
with typed fields. Each cron run processes at most 50 entities of one type.
Rebuilds lock and reload each source before reading its fields. Normal saves
update the index immediately. To replace the entire cache from current values:

```php
\Drupal::state()->set('typed_reference_index.rebuild', []);
```

Run cron until the state key is deleted. Typed reverse lookup is incomplete
while rebuilding; direct entity-reference queries are unaffected. Clearing or
uninstalling the index never deletes source references. Removed source values
cannot and should not be reconstructed by this cache.
