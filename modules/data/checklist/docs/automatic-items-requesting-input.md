# Automatic checklist items that request input

Use this example when implementing an automatic checklist item that spans requests,
then needs a person or API client to supply missing information. The executable
example is `input_progress_example` in
`tests/modules/checklist_reader_test/src/Plugin/ChecklistItemHandler/InputProgress.php`;
its Drupal plugin form is `PluginForm/InputProgressForm.php` in the same test module.
`tests/src/Kernel/ChecklistInputProgressTest.php` exercises both input paths.
This is a test/example plugin, not a production document extraction integration.

## One item, one attempt, three entry points

1. `actionIteration()` starts/polls the provider. It returns `ChecklistItemResult`
   with declared working-state changes and `WAITING` when more work is needed.
2. `getActionState()` projects only safe public information from stored state. It
   returns `inputRequired: TRUE` when input is needed. It must never call the
   provider, save data, acquire a claim, or advance execution.
3. The `action` PluginForm and `actionOperations()` offer that input through HTML
   and APIs. Both call the same handler method for domain validation and submission.

The example processes four of five documents and requests a document reference.
Submitting the reference queues the continuation; the next worker iteration
publishes the reference as an outcome and completes the item. Completion clears
working state through the normal item lifecycle. Failure retains state.

## Handler contracts

Implement these existing interfaces alongside `ChecklistItemHandlerBase`:

- `IterativeChecklistItemHandlerInterface`: bounded `actionIteration()` results.
- `StatefulChecklistItemHandlerInterface`: typed `stateDefinitions()` for provider
  run IDs, intermediate progress and supplied input.
- `ExpectedOutcomeChecklistItemHandlerInterface`: published result definitions.
- `ActionStateChecklistItemHandlerInterface`: public progress and input request.
- `ActionOperationsChecklistItemHandlerInterface`: operation schemas and execution.

Return `ChecklistItemInterface::METHOD_AUTO` from `getMethod()`. Declare the plugin
form in the handler annotation:

```php
forms = {
  "action" = "\Drupal\your_module\PluginForm\DocumentInputForm"
}
```

No row form is required for this input path. The checklist formatter embeds the
`action` form below an actionable automatic item while it requests input. It uses
core's plugin form factory, including the existing custom wrapper form contract.
When input is no longer required, reconciliation removes the form. While input
is still required, reconciliation preserves the existing form and unsaved edits.
A stale form may therefore need a reload; it must not silently acquire a new
attempt version and overwrite newer work.

The input-required flag should describe the same execution state for all permitted
callers, including the stored worker account; only the safe message may be
viewer-specific. The input-required flag is not permission and does not override applicability,
actionability, required contexts, host/field access or item operation access.
The action-form route applies those gates too. View-only users can see safe
progress without receiving a writable form. Do not put raw provider responses,
prompts, credentials or personal information into the public message.

## Shared input submission

Inject `checklist.item_executor` and `checklist.attempt_journal` into the handler.
The example's `supplyReference()` validates the reference, then calls:

```php
$queued = $this->executor->acceptInput(
  $this->getItem(),
  $expected_attempt,
  ['reference' => trim($reference)],
);
```

This is an **internal plugin API**, not an HTTP endpoint for arbitrary state.
Explicitly map validated domain input to declared state fields. Never pass an
unfiltered API request or all form values as working state. Provider calls belong
in the next iteration, outside the short input transaction.

The executor reloads the saved binding and current contexts, rechecks permissions
and readiness, and verifies the automatic handler is still requesting input.
The attempt coordinator requires the expected waiting attempt/version, rejects a
concurrent worker claim, validates typed state, and commits the state update with
a `waiting -> queued` history event attributed to the submitting user. It retains
the attempt UUID and original executor. A queued continuation is immediately due
for the existing scheduler; submitting input does not execute the provider inline.
An old queue message carries the old version and cannot overwrite this update.

Keep the attempt ID and version that were shown with the request:

- The HTML example stores them in Form API `value` elements in cached form state.
- The API schema advertises them with `const` constraints and requires the client
  to return them with the input. The dispatcher validates the current schema.
- On a stale request, reload/discover again. Do not retry with a newly loaded
  version automatically on behalf of a stale form or client.

## Operations are explicitly defined

`inputRequired` does not generate operation schemas from Form API or select an
operation automatically. Implement `actionOperations()` to expose the valid
operations for the current stage. The example advertises:

```json
{
  "operation": "supply_reference",
  "instance_uuid": "<checklist instance UUID>",
  "generation": 1,
  "expected_version": 0,
  "parameters": {
    "reference": "DOC-42",
    "attempt_id": "<attempt UUID from discovery>",
    "version": 3
  }
}
```

The versions above are illustrative. Use the acquired workspace lease generation
and version for the transport envelope, and the discovered attempt version in
`parameters`; these are separate concurrency checks. The
optional Checklist API module provides the transport. The operation dispatcher
also supports non-HTTP callers such as AI tools, under the current caller's access.
The action form is a thin adapter to the same handler method; avoid duplicating
validation or persistence in its submit callback.

## Worker and refresh behavior

A worker delivery while the stored projection requires input returns without
claiming or invoking `actionIteration()`. The current scheduler can still deliver
these waiting attempts periodically; no provider calls or new history transitions
are produced while input is outstanding. There is no dedicated scheduler index for human-input waits.

Saved, untranslatable default-revision checklists poll their rows every five
seconds while actionable automatic work has a progress provider and does not
require input. The input form therefore appears when a worker requests it; polling
pauses for that item until a local submission restarts it. Changes made elsewhere
while all items await input require a reload. Progress also refreshes on page load
and checklist AJAX actions. Hidden tabs and active AJAX requests defer polling;
transient failures back off up to a minute, and access/instance failures stop it.
Kernel tests cover form/API submission, access/readiness, stale claims, shared
working state, history and continuation. Real screenshots in
`docs/screenshots/checklist-progress` at the repository root show the Drupal UI.
