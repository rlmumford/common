# Reading operational history

`checklist.item_reader->readHistory($host, $field_name, $delta, $item_name,
$attempt_id = NULL, $after_version = 0, $limit = 50)` returns audit metadata for
one attempt. It accepts the host entity, including an authorized working entity,
just like the item-state reader. It does not execute handlers, acquire leases,
save items or create attempts.

The optional **Checklist API** module exposes this through:

```
GET /checklist/{entity_type}/{entity_id}/{field_name}:{delta}/{item_name}/history
GET .../history?attempt={attempt_uuid}&after_version=2&limit=50
```

Both GET and HEAD are supported; use GET to retrieve the JSON body. Responses are
private and non-cacheable. Host view, field view and item `view action state`
access are required, using the current account. A read-only viewer can inspect
history without owning the editing workspace. Item-access hooks can deny this
along with other action-state reads. Hidden items and unknown items both return
404; an attempt belonging to any other item also returns 404.

The response contains:

- `item` and `instance_uuid`: the current item name and saved checklist identity.
- `attempt`: ID, predecessor ID (`previous`), mode, execution status, version,
  initiator/executor user IDs, entry path/operation and creation/change timestamps.
  This is `null` if no attempt has been recorded.
- `events`: transitions in version order, each with `version`, `from_status`,
  `to_status`, `actor` user ID, `created` timestamp and safe-text `reason`.
- `next_after_version`: the last returned event's version, or the input cursor
  if there are no new events.

With no `attempt` parameter, the latest attempt is selected. **Pin the returned
attempt ID when paging** so a new attempt cannot switch the stream midway through
a read. Pass `next_after_version` as the next request's `after_version`; it is an
exclusive cursor. The limit defaults to 50 and must be 1–100. An empty page means
there are no later events at the time of the read; a nonterminal attempt may append
more later. Attempt metadata and events are live reads, not a transaction snapshot.
Follow `previous` to read older attempts; no unbounded history is loaded.

User IDs are audit identities, not grants to load user profile data. An input
transition records the person who supplied the input while the attempt retains
its original initiator and executor. Timestamps are Unix seconds. Render reasons
as plain text. Journal writers must not put exception dumps, prompts, credentials
or working state in reasons. The API excludes intermediate state, outcome values,
context snapshots, execution bindings and claim tokens.

This endpoint exposes **recorded durable attempts**, currently used by iterative
automatic items. It does not invent history for legacy synchronous actions that
have not entered the attempt journal, nor does it offer retry/reset operations.

## Example: automatic extraction requests input

This illustrative timeline represents one attempt, not multiple attempts:

| Time | Transition | Actor | Meaning |
| --- | --- | --- | --- |
| 10:00:00 | → queued | Initiator | Work was submitted. |
| 10:00:01 | queued → running | Executor | A worker began the first iteration. |
| 10:00:03 | running → waiting | Executor | The handler paused; its action state requests input. |
| 10:02:00 | waiting → queued | Staff member | Requested input supplied. |
| 10:02:01 | queued → running | Executor | A worker began the continuation. |
| 10:02:04 | running → succeeded | Executor | The attempt completed successfully. |

Each transition has its own monotonically increasing version and timestamp.
The public API represents actors as numeric user IDs. A `waiting` transition
alone does not distinguish waiting for a person from waiting for a provider;
use the item's current action state for its live progress/input requirement.
History is an audit of attempt transitions, not a trace of every internal handler
step or every progress update.

For example, the input event could be returned as:

```json
{
  "version": 4,
  "from_status": "waiting",
  "to_status": "queued",
  "actor": 14,
  "created": 1790762520,
  "reason": "Requested input supplied."
}
```

If an attempt fails, that failure remains in its history. A separately created
resume/fresh attempt has its own ID and events, linked through `previous`.
Reading history neither creates that successor nor retries the action.

## Relationship to an item completion timestamp

History complements a single item-level completion/actioned timestamp: the latter
answers when the item was completed, while history explains the recorded execution
attempts leading to that result. For a journaled successful attempt, its
`succeeded` event gives that attempt's completion time. It does not necessarily
represent the current item disposition after subsequent reopening or manual edits.

The current D10 checklist item defines status and completion method but does not
have a dedicated `actioned`/completion datetime field. This PR does not add or
remove one. History cannot currently substitute for such a field across all items:
manual decisions and other unjournaled synchronous actions are not covered. A
consistent completion timestamp for all completion paths remains separate work.
