# Document-related work

Enable `document_task_links` to add a **Related tasks** display component to
all document bundles and **Related documents** to the task workspace. This is
optional; it does not change task execution or document approval.

The `document_related_tasks` View lists distinct tasks of every status. It uses
an indexed document contextual filter and retains task query access for rows,
searches and pager counts. The contextual filter checks document view access.
The reverse task page checks both entities again before showing labels/links.
Relationships are task-level metadata. Source checklist titles are additionally
subject to item action-state and outcomes-field access. Raw outcome payloads,
internal working state and file URLs are never rendered by this integration.

## Authoritative references

The index reads saved direct document-reference fields on tasks, typed task
`context` values, and checklist item `outcomes` whose checklist host is a task.
`task_context.entity_references` walks their declared entity/list/map definitions,
including nested serialized values. It does not guess entity IDs in arbitrary
text, primitive values or untyped maps, or recursively inspect entity fields.
Handlers must declare their output data definitions to establish relationships.

A task can use and produce the same document, or reference it through several
contexts/items. The View shows the task once and describes each recorded link.
Saved output references establish links even before a task is resolved; this
feature does not assert that an output was approved or successfully handled.

## Recorded history

The `document_task_link` table contains observations, not a second source of
reference values. Source save hooks reconcile its current links inside the SQL
entity save transaction. Removed/replaced links become **Former Input/Output**;
re-adding a link creates a new observation. Deleting a checklist item closes its
outputs. Deleting a task or document removes its observations.

`first_seen` means the time this integration observed the relationship, not the
original task creation time. `observed_revision` is the document revision seen
at that point; it is explicitly labelled **Observed revision**. Ordinary
references identify a document, not immutable bytes. Changing a document does
not rewrite existing observations or claim that a task pinned the new revision.
The document page lists relationships across the document's revisions.

Installation schedules a bounded cron backfill (50 tasks and 50 items per run).
Existing references begin their recorded history at backfill time. There is no
reconstruction of changes from before tracking was enabled. To reconcile the
current index again, preserving observations and their timestamps:

```php
\Drupal::state()->set('document_task_links.backfill', [
  'task' => 0,
  'checklist_item' => 0,
]);
```

Then run cron until that state value is empty. Rebuilds lock each source before
reloading it. Do not truncate the table: removed relationships cannot be
reconstructed from current reference fields. Normal source saves do not depend
on cron. Uninstalling the module removes its observation history.

## Consumer extension points

- `hook_task_ui_task_summary_alter(&$build, $task)` extends the read-only task
  summary on canonical and inline workspaces. It refreshes after board actions
  without replacing checklist forms. This module adds related documents there.
- `hook_document_task_links_compatible_alter(&$allowed, $task, $document)` adds
  relationship-level constraints during indexing and rendering. Denials should
  be combined with the existing result, never overwritten with an allow.
- `hook_document_task_links_query_alter($query, $document)` adds equivalent SQL
  conditions to the indexed subquery used by Views. Its task table alias is
  `related_task`, relationship table alias `l`. These conditions apply before
  pagination. Keep them consistent with the compatibility hook.

CounselKit's `ck_organization_task` supplies organization compatibility and SQL
constraints, as well as validating typed contexts and outcomes on writes.
