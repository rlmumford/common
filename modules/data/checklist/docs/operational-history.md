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
