# Checklist interaction contract

Status: agreed direction; implementation remains staged. Updated 18 September 2026.

UI forms, HTTP clients and AI tools interact with the same checklist, working state
and ownership rules. This document extends the [workflow architecture](WORKFLOW_ARCHITECTURE.md)
and [implementation plan](WORKFLOW_IMPLEMENTATION_PLAN.md). The existing action
operation dispatcher is only the preparation/gating layer; it does not yet implement
this workspace, read model, locking or HTTP contract.

## Addressing and HTTP surface

The optional HTTP submodule is `checklist_api`. Its proposed base URL is:

```
/api/checklist/v1/{entity_type}/{entity_id}/{field_name}/{delta}
```

The field belongs to the host entity. Always include a delta, including zero for a
single-value field. Item names are scoped to a checklist, not global entity IDs.

| Method and relative path | Contract |
| --- | --- |
| GET `/` | Authorized checklist description, item summaries and links. |
| GET `/items` | Authorized item summaries, keyed by item name. |
| GET `/items/{item_name}` | Current item status, readiness and safe action progress. |
| GET `/items/{item_name}/action-operations` | Currently available action operations and parameter schemas. |
| POST `/items/{item_name}/action-operations` | Invoke the operation named in the JSON body. |

Example execution body:

```json
{
  "operation": "choose",
  "parameters": {"choice": "approve", "reason": "Reviewed"}
}
```

Ownership/version preconditions accompany mutations; their exact wire format will
be fixed with the workspace implementation. A successful synchronous invocation
returns a structured result and refreshed item/operation links. Durable execution
will return an accepted response with an attempt identifier and polling link once
attempt storage exists. Do not report acceptance before the work is durable.

GET supplies discovery JSON. HEAD has no response body; OPTIONS describes HTTP
capabilities, not the item operation catalogue. See
[HTTP semantics](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.3.2).
GET, including progress polling, must not acquire ownership, execute an action,
advance a continuation, or complete an item.

The shared resolver lives in Checklist. It accepts the entity object (including
unsaved entities) and field/delta; route upcasting or worker loading happens in the
adapter. It preserves supplied revision/translation and in-memory state rather than
reloading the host. Saved hosts use view/update access; new hosts use create access.
Field access still applies. Workspace adapters select and attach the authoritative
shared checklist graph; resolution must not silently fetch a different tempstore
snapshot. Item membership checks belong to the item reader/dispatcher. Scope persisted item
queries by checklist type as well as host ID and checklist key to prevent collisions
between host entity types using the same numeric ID and field name.

Current keys use a field name and optional delta. Delta is a location, not durable
identity: reordering can retarget an old URL. Before durable bindings are exposed,
introduce stable checklist-instance identity or enforce a no-reordering policy with
identity/version checks. A stale address must never execute against replacement
work. Translation/revision targeting also needs an explicit policy before exposure;
initial APIs must not infer it from an arbitrary viewer's language or draft context.

## Item reads and live action progress

An item read distinguishes these concepts:

- Durable item disposition: incomplete, complete, failed or not applicable.
- Computed applicability, actionability and requiredness, with safe explanations.
- Current execution attempt: queued, running, waiting, succeeded, failed or cancelled,
  when an attempt exists; this does not replace item disposition.
- Safe progress: stage, message, optional meaningful completed/total or percentage,
  update time, and whether input is needed.
- Workspace ownership/version and permitted next actions, subject to access.
- Outcomes and resource references that the caller is authorized to see.

An optional handler capability should provide a structured action-state/progress
projection (working name `ActionStateChecklistItemHandlerInterface`). A shared item
reader supplies generic status and attempt information and asks the handler only
for plugin-specific presentation. Final method names and result schema belong in
that implementation slice. Handlers without this capability still have useful GET
responses from the generic reader.

The projection reads stored working state and attempt/run records. It does not
serialize the entire state bag or expose raw prompts, credentials, internal tool
payloads or unrestricted errors. Plugins can expose safe intermediate values that
help the user understand or continue the action. Refreshing an external provider's
status belongs to execution/continuation work, not a supposedly read-only GET.
Include an update timestamp so callers can distinguish stale progress from activity.

Discovery remains a current-account snapshot, never authorization for later
execution. Reads can be permitted to a viewer who cannot edit; that viewer gets no
mutation authority. Shared read/access policy applies to UI, HTTP and AI results.

## Shared workspace and user ownership

Workspace addressing uses host entity type, host UUID and checklist field/delta
key, never the numeric host ID. A UUID assigned when an entity is created permits
cross-request tempstore storage before the host is saved. Saving the host keeps
that address stable, and distinct unsaved hosts must remain isolated. The existing
repository already uses this UUID key; regression coverage proves pre-save storage,
isolation and retrieval/deletion through the same address after saving.

Stable addressing does not update serialized snapshots automatically. After saving
a host, the workspace coordinator must update/rebind the stored graph explicitly;
it must not later treat an old unsaved snapshot as a new host to insert again.
Tempstore remains subject to its lifetime policy; durable attempt/state storage is
still required for long-running work. Stable checklist identity across delta moves
remains separate from stable host identity.

There is one authoritative interaction workspace per checklist, shared across UI,
API and AI. Do not create separate per-channel or per-user copies of checklist item
working state. Access alone permits observation; mutation additionally requires the
current ownership lease and matching version. Opening or polling a task does not
silently take the lock.

Initial policy is one editing owner per checklist. Ownership is user-scoped:
that user's UI, API client and authorized AI tools act through the same workspace.
Two tabs or tools belonging to the same user still need version checks to avoid
last-write-wins overwrites. Other authorized users may observe progress but receive
a conflict if they attempt to mutate without ownership.

Acquisition, renewal, release and takeover are explicit shared-service operations,
with thin UI/API/tool adapters. Takeover requires a separate policy/access decision,
is visible to the previous owner, and records who took over, from whom, when and
why. Ordinary host update access must not implicitly grant force-takeover access.
The UI presents an explicit takeover action when allowed. Lease duration, renewal
interval and takeover permission defaults will be chosen during implementation.

Acquiring or taking over ownership is atomic. Each new ownership grant increments
a generation/fencing token. Every mutating path checks the active owner, generation
and workspace/item version at commit time, not only when rendering a form or
starting a request. Release/expiry revokes the old grant; reacquisition creates a
new generation even for the same user. Old forms, API calls and delayed AI results
cannot save after takeover or expiry. Return a conflict and current read links so
the client can refresh deliberately.

Temporary storage may cache editing data, but must not be the only copy of durable
working state, pending run IDs or attempts. Tempstore expiry must not discard a
long-running operation or failed state needed for explicit resume. Preserve the
agreed failure policy: retain working state on failure, clear it on success, and
create separate attempts for resume/start-fresh.

Proposed takeover default, following the user's latest direction: start fresh rather
than inherit the previous owner's partial interaction. Atomically supersede the old
interactive attempt(s), revoke their write authority, clear uncommitted form/editing
state and the affected items' active working state, and create fresh successor
attempts when work restarts. Completed items, committed outcomes and operational
history remain intact. Do not reset unrelated autonomous attempts merely because
the editing owner changes. The exact set of affected interactive items must be
explicit and covered by takeover tests.

This is distinct from explicit resume after failure, which may use retained state.
Do not retain unrestricted raw state dumps in history merely to support takeover;
apply the established access/redaction/retention policy. Clearing local state never
rolls back external effects or automatically authorizes repeating them. Items whose
previous attempt may already have performed an external action require reconciliation
before a fresh attempt repeats that action.

## Workers, tools and concurrency

Workspace ownership and execution claims solve different problems. Ownership
coordinates human/agent editing of a checklist. Per-item attempt claims prevent
concurrent execution of the same work and allow independent items to run in
parallel. Neither lock substitutes for the other; long network calls must not hold
a database transaction open.

Interactive AI work binds its checklist/item reference, owning user, ownership
generation, attempt and expected version server-side. The model receives the
permitted operation description and parameters, not authority to choose an arbitrary
host or impersonate an executor. A tool may span several checklists only through an
explicitly authorized target scope. The shared resolver and dispatcher recheck the
binding at invocation and result application without an HTTP loopback.

An autonomous worker may have a separate declared execution policy and no human
workspace owner. It still coordinates conflicting state writes through the common
version/claim rules. It must not impersonate the editing owner merely to bypass a
lease, and merely viewing a task must not pause independent automatic work.

Takeover revokes old interactive writes, but cannot undo an external side effect or
guarantee a running provider request has stopped. Record late completion against its
original attempt and require reconciliation or an explicit successor continuation
before it can affect current state. A fresh takeover does not adopt that result
implicitly. Do not automatically repeat the external action.
Initiator, executor and workspace owner remain distinct audit identities.

## Access and implementation order

Use a common access policy for host view/update, checklist field view/edit, item
visibility, operation-specific permissions, workspace ownership and takeover.
Transport authentication/CSRF belongs to adapters. Ownership never grants missing
entity/field permissions. Recheck current permissions and account status at write
and result application. Avoid disclosing hidden items or state through discovery,
progress, conflict responses or AI context.

Implement in this order:

1. Rename the shared dispatcher to `ChecklistActionOperationDispatcher`, service
   `checklist.action_operation_dispatcher`, matching handler terminology.
2. Implement shared reference resolution and item reads, with field access, host
   isolation, multiple fields/deltas and explicit workspace semantics. Entity-based
   resolution is available through `checklist.resolver::resolve()`: host/field access,
   type checks, field/delta isolation, unsaved entities and in-memory state are
   covered. Callers own loading/workspace selection; shared workspace composition
   and the item read model remain open.
3. Implement durable state/attempt storage, ownership leases, atomic takeover,
   version checks and operational history; integrate all mutating paths.
4. Add `checklist_api` discovery, reads and invocation, then AI tool adapters using
   the same services. Enable external mutations only with stale-write and duplicate
   invocation protection; discovery/polling can be delivered earlier.

Required acceptance scenarios include two users viewing one checklist; denied edits
while another owns it; same-user UI/API/tool continuity; concurrent same-user tabs;
authorized and denied takeover; old-owner submissions and late AI completion;
lease expiry without unintended state loss; fresh takeover clearing only the
affected working state while preserving committed outcomes/history; changed permissions; multiple fields and deltas;
unknown/hidden items; no side effects from GET; safe progress with no raw-state
leakage; parallel independent attempts and conflicts on shared state.
