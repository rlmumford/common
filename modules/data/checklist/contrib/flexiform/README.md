# Flexiform checklist items

Enable `checklist_flexiform` to collect structured input in a dedicated `flexiform`
item. Manual items remain instruction-and-confirmation items. This integration
ships with `rlmumford/checklist` and requires Flexiform `3.0.x-dev` (shared form
sessions and the referenced form plugin).

Enable `checklist_flexiform_ui` for authoring in the job checklist editor. Choose
Standard, Wizard, or Referenced and use the native Flexiform configuration form.
After changing the form definition, select **Update form configuration** to
refresh input mappings and published outcomes. These edits stay in the job draft
until Save. Runtime sites do not need the UI module enabled.

## Example

This embedded form edits the task title. Put this under the job's checklist items:

```yaml
details:
  title: Review task details
  handler: flexiform
  handler_configuration:
    form:
      plugin: standard
      configuration:
        data:
          task:
            plugin: provided
            entity_type: task
            bundle: task
            save_on_submit: true
        components:
          title:
            component_type: typed_data
            context: task
            path: title.0.value
            label: Title
    context_mapping:
      task: 'checklist:entity'
    outcomes:
      reviewed_task: task
```

For a reusable form, replace `form` with:

```yaml
form:
  plugin: referenced
  configuration:
    form_id: task_details
```

`provided` and `provided_data` bindings become required checklist input contexts.
The standard `context.handler` resolves their `context_mapping`; Typed Data Plus's
context-assignment extension supplies property navigation and filters when
installed. Other providers resolve their own configured contexts inside Flexiform.
Configuration editing inspects definitions without executing providers.

`outcomes` maps outcome machine names to whole form-data bindings. Definitions
come from the form's expected contexts. Later checklist items can use, for example,
`item:details:reviewed_task.title.value`. A transient `provided_data` binding can
collect a typed value solely as an outcome, without saving an entity.

## Execution and API

The task's Start control opens the form inline. It automatically runs the first
bounded preparation pass; forms that need more work show the existing preparation
screen and advance automatically. The budget is cooperative: providers must yield
between expensive steps rather than blocking inside one call.

HTML and action operations share one authoritative session in private checklist
item state. No separate form tempstore is created. The authenticated account owns
the attempt, and each mutation checks the advertised revision, current access and
checklist gates. First interaction materializes an item that previously existed
only in job configuration. Discovery alone does not persist it.

The usual checklist operation dispatcher exposes:

- `get`: current status, data, schema and available actions; no mutation.
- `start`: begin preparation, with `revision: 0` for new work.
- `advance`: another preparation pass, with the current revision.
- `form/<action>`: the advertised action, revision and input. Examples include
  `form/update`, `form/next`, `form/submit` and `form/finish`.

Use the returned schema and actions rather than assuming every form has the same
buttons. HTTP endpoints remain in the optional Checklist API module. The Entity
Template checklist editors use this same coordinator and HTML adapter, retaining
their template choice and prepared-entity persistence rules.

## Saving, failures and supported forms

Update and wizard navigation retain private working values. Submit/Finish requests
the configured provider saves; these run only inside the checklist's fenced result
commit. Successful completion publishes outcomes and clears intermediate state.
An exception preserves previously accepted state and records a failed attempt;
it does not silently retry or give another user ownership.

Providers must perform short local transactional saves. External communications
and other irreversible effects belong in separate checklist items. Submit
enhancers are rejected because they could introduce effects before the fenced
commit. Form plugins and components must support Flexiform's API contract, so
HTML and API clients see the same fields and validation. This does not make every
Drupal field widget API-capable.

The existing execution binding restrictions apply: saved hosts, single-valued,
nontranslated checklist fields, and no revisioned checklist binding. Retained
entities use ordinary save semantics; detecting concurrent external edits across
form requests remains a future configurable Flexiform save policy.

## Tests

Kernel tests cover shared HTML/API ownership, stale revisions, invalid input,
resumable preparation, deferred wizard saves, failures, first materialization and
outcomes feeding later items. The functional test authors a referenced form in a
job draft and completes it against a task. The PR screenshots show a real local
Drupal task before and after completion, captured with Playwright.
