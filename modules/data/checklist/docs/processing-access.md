# Access when processing a checklist

`checklist.processor->process($checklist)` executes under the current Drupal
account. The processor checks host and checklist-field access through the same
resolver used by item reads and action operations. The checklist key must identify
an actual checklist field (`field_name` or `field_name:delta`) whose plugin type
matches the supplied working graph.

The caller still owns loading the host and selecting the authoritative working
session. The processor preserves that working graph, including unsaved item edits;
it does not replace it with a second graph or grant access because it came from
tempstore. For a saved host, processing requires host view/update and field
view/edit access. An unsaved host uses the resolver's create-access policy.

For synchronous automatic items, `execute iteration` item access is checked before
preparing contexts, evaluating gates or invoking `action()`. An item-specific
denial skips that item without changing its status, outcomes or attempt history.
Required unfinished work continues to prevent checklist completion; denied optional
work does not become required merely because it is inaccessible.

Host/field access is checked before processing begins, before each synchronous
item, and before checklist completion. A denial raises `AccessDeniedHttpException`
outside the handler exception path: it must not be converted into a failed item.
If access is revoked after one item has completed, later items stop; effects of
already executed work are not undone by this access check.

Iterative automatic items continue to use the executor's fresh host/item loading,
active-account checks, claims and commit-time authorization. This change adds the
missing checks for synchronous work; it does not give synchronous handlers those
durable execution guarantees or introduce automatic retries.

A cron/worker caller must establish an authorized execution account before invoking
the whole-checklist processor. Running in cron does not itself grant permission.
Per-item iteration workers retain their existing stored-executor account switching.
