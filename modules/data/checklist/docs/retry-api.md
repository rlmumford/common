# Retrying failed automatic items through the API

Enable the optional `checklist_api` module. Retry is a lifecycle request rather
than a handler-defined action operation. It uses the same executor as the inline
Retry form and always queues work; the HTTP request does not run the handler.

## Request sequence

Use the site's authenticated session and JSON format (`?_format=json`). For a
session-authenticated mutation, obtain `/session/token` and send its value in
`X-CSRF-Token`. Do not put tokens in URLs or logs.

1. `GET /checklist/{entity_type}/{entity_id}/{field[:delta]}/{item}` obtains the
   visible item state and persisted `instance_uuid`.
2. `GET /checklist/{entity_type}/{entity_id}/{field[:delta]}/{item}/history`
   obtains the latest attempt's `id`, `version`, `status` and `path`. Retry requires
   a failed automatic `action` attempt. Review the failure before retrying.
3. `POST /checklist/{entity_type}/{entity_id}/{field[:delta]}/workspace` with
   `{"instance_uuid":"<instance UUID>"}` acquires or resumes your workspace.
   Retain its `generation` and `version`. Renew it through the existing workspace
   API when needed; a retry request does not renew its expiry.
4. Submit the exact reviewed attempt and workspace versions:

```text
POST /checklist/{entity_type}/{entity_id}/{field[:delta]}/{item}/retry?_format=json
Content-Type: application/json
X-CSRF-Token: <session token>
```

```json
{
  "instance_uuid": "<instance UUID>",
  "generation": 1,
  "expected_version": 0,
  "attempt_id": "<failed attempt UUID>",
  "attempt_version": 3,
  "mode": "resume"
}
```

Choose `resume` to retain working state or `fresh` to clear working state. Both
create a new attempt linked to the predecessor and preserve outcomes and history.
Neither choice reverses external actions taken by an earlier attempt.

## Success and subsequent work

`202 Accepted` means the successor was recorded for background execution:

```json
{
  "attempt": {
    "id": "<new attempt UUID>",
    "previous": "<failed attempt UUID>",
    "mode": "resume",
    "status": "queued",
    "version": 1
  },
  "workspace": {
    "instance_uuid": "<instance UUID>",
    "generation": 1,
    "version": 1,
    "expires": 1800000300
  }
}
```

The initiating and executing account is the authenticated caller; the payload
cannot choose another account. The normal scheduler/worker runs the successor.
Poll the item-state endpoint for safe progress and the history endpoint for
attempt transitions. No working-state dump, claim token or provider payload is
returned. Responses from the retry endpoint are private and non-cacheable.

## Rejections and concurrency

- `400`: malformed JSON or missing/incorrectly typed fields, including unknown
  retry modes. Versions must be JSON integers, not strings.
- `403`: authentication, CSRF or current host/field/item access does not permit
  the request. Hidden or missing items can return `404`.
- `409`: the workspace owner, generation, version or checklist instance changed;
  the reviewed attempt changed; or the work is not currently eligible to retry.

Workspace admission uses the same version fence as action operations. An admitted
request consumes that version even if the executor subsequently rejects readiness
or a concurrent change. Refresh workspace status and item/history after an error;
do not blindly increment versions or replay an old packet. A lost success response
also requires a refresh: replay returns a conflict instead of making another
attempt. Workspace admission and executor persistence are separate operations,
not a cross-backend atomic transaction or an ongoing execution lock.

The executor independently rechecks current account, host, field, item and gates,
then atomically creates the successor and resets the item. Unsupported bindings
are rejected: this adapter supports saved autonomous items on single-value,
untranslated, non-revisionable checklist fields, including such fields on
revisionable hosts. Completed items cannot be reopened by this endpoint.
