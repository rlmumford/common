# Checklist Entity Template

Enable `checklist_entity_template` to use the
`entity_template__create` and `entity_template__apply_to` checklist item handlers.
Install Entity Template's `1.0.x-dev` branch, which includes typed targets and
resumable execution:

```sh
composer config repositories.entity-template vcs https://git.drupalcode.org/project/entity_template.git
composer config repositories.flexiform vcs https://git.drupalcode.org/project/flexiform.git
composer require drupal/entity_template:1.0.x-dev drupal/flexiform:3.0.x-dev
```

This optional integration ships inside `rlmumford/checklist` and requires
Flexiform 3.0.x with shared sessions and the `referenced` form plugin.
Both dependencies track their development branches without commit pins.
Entity Template's source resolver is available on its 1.0.x development branch.

## Configuration

`templates` is a sequence keyed by candidate machine name. Each candidate owns
its template source, availability condition, parameter mappings and optional
prepared-entity editor. For example, inside `default_items`:

```yaml
create:
  title: Create the follow-up record
  handler: entity_template__create
  handler_configuration:
    templates:
      article:
        condition:
          id: condition_constant:true
        template:
          type: embedded
          configuration:
            id: standalone
            label: Follow-up article
            target_entity_type_id: node
            target_entity_bundle: article
            parameters:
              title:
                type: string
                required: true
            components:
              title:
                id: property_context
                path: title.0.value
                context_mapping:
                  value: title
        context_mapping:
          title: checklist:entity.label
```

A referenced candidate replaces only its `template` property:

```yaml
template:
  type: referenced
  configuration:
    blueprint: follow_up
    template_id: main
```

Drupal config schema selects the configuration shape using `type`. This avoids
ambiguous combinations of reference IDs and embedded plugin configuration. It
uses dynamic schema types, not JSON Schema `oneOf` and not another plugin manager.

Mappings use checklist contexts and Typed Data Plus selectors, including global
`@provider:context` selectors. Inputs are scoped to their candidate; two templates
can use the same parameter name with different definitions and selectors. Missing
required inputs make only that candidate unavailable. Components receive the
chosen template's parameters and live `self`. Replace the sample selector with a
property exposed by the actual host and configure other required entity fields.

A candidate must pass both its optional `condition` and the template's own
conditions. One available candidate without an editor runs automatically. An
editor makes that candidate interactive; multiple available candidates first
present a choice in HTML/API. No candidates means the item is not actionable.
The selected template and editor are captured before preparation and stay selected
across pending passes. This follows the D7 choice/per-template-editor behaviour;
the D7 missing-parameter input form and form-bypass option are not ported here.

All alternatives must return one fieldable entity of the same entity type;
bundles may differ. The stable `entity` outcome definition includes their bundle
union. Referencing a blueprint member does not run its sibling templates, so
previous blueprint results are unavailable. Builder contexts remain available.
Entity Template owns source resolution and template dependency collection;
Common adds the selection-condition and editor dependencies.

This slice provides configuration schema and runtime handling; it does not add
a template selector/editor to the checklist configuration UI.

## Automatic execution and outcomes

Submit through `checklist.item_executor` or process the checklist normally. The
existing runner's binding restrictions apply: persist the host and item first,
and use a single-value, untranslated checklist field on a non-revisionable host.

Short templates finish in the initial request. Each template pass has a two-second
cooperative preparation budget; this does not interrupt a blocking provider call.
A pending template returns a waiting attempt, eligible for the existing scheduler
one second later. It resumes the captured template, target, inputs and component
state under the original executor's current permissions. Template configuration
changes do not replace already captured work. Missing or incompatible referenced
configuration can still block the runner's input checks.

Preparation happens outside the result transaction. After rechecking the live
claim, item, inputs and access, a short result callback validates and saves the
entity on the checklist database connection, then stores the `entity` outcome
and completion together. The callback is request-local; it is never serialized
as working state. Entity save hooks must not introduce external effects that a
local database rollback cannot undo.

Later items can map contexts such as `item:create:entity` or
`item:create:entity.title.value`. No entity outcome is published while preparation
is pending or failed. Completion clears intermediate state. Preparation failures
retain a private snapshot; safe progress and history omit provider diagnostics.
Commit failures retain the last committed working state and record a failed
attempt requiring reconciliation. Automatic retry and explicit start-fresh/resume
commands are not introduced by this integration.

## Shared editing before save

Add `editor` to a **candidate**, alongside its `template` and `context_mapping`:

```yaml
editor:
  plugin: standard
  configuration:
    data:
      entity:
        plugin: provided_data
    components:
      title:
        component_type: typed_data
        context: entity
        path: title.0.value
        label: Title
```

A saved form uses the same plugin configuration contract:

```yaml
editor:
  plugin: referenced
  configuration:
    form_id: review_article
```

A wizard uses `plugin: wizard` and its normal `pages`. The single binding must be
transient `provided_data` named `entity`; do not configure `save_on_submit`, extra
providers or save enhancers. The checklist owns the final save. Components must
support Flexiform's API contract; custom schemas need an HTML adapter. Standard
entity field widgets are not implicitly API-capable.

`PreparedEntityEditor` coordinates editing the unsaved **entity produced by a
template**. It is not a template configuration editor. It belongs in the optional
checklist integration because it owns checklist claims, revisions, authorization,
audit and completion. Reusable working sessions and HTML controls live in
Flexiform; template resolution and preparation live in Entity Template.

With multiple candidates, the action form asks the user to select a template.
With one candidate, opening the existing checklist action form bootstraps preparation through a
CSRF-protected submission. JavaScript advances that submission automatically;
non-JavaScript users have an Open form button. The first pass uses the existing
two-second cooperative budget. A short template goes straight to its fields;
only pending work displays Preparing and polls through protected AJAX submits.
Next/Previous keep the same graph, and Finish saves once. Editor preparation is
advanced by the HTML/API caller; the automatic-item scheduler does not claim
interactive operations.

The named action operations are:

- `get`: current status, revision and, when ready, values/schema/UI/actions.
- `start`: initial preparation, with `revision: 0` and a candidate `template`
  machine name. The name is required when several candidates are available.
- `advance`: another preparation pass, with the current revision.
- `form/<action>`: a currently advertised Flexiform action, with `revision` and
  `input`. Examples include `form/update`, `form/submit`, `form/next`,
  `form/previous`, `form/finish`, and component actions.

Use `checklist.action_operation_dispatcher` for non-browser interactions; the
integration supplies this operation contract, not a new public HTTP route. The
HTML form uses the same coordinator and working graph. Description has no side
effects. Submitted revisions are checked against the durable attempt version,
so an old browser form cannot overwrite a newer API edit, and vice versa. Duplicate
mutations with an old revision are rejected; read current state before retrying.
Final completion clears private state and publishes the saved `entity` outcome.

One attempt owns the editor for one authenticated user. Even another authorized
administrator cannot read or mutate its working data. Automatic takeover, reset
and recovery commands are not included. Failed operations retain the last
committed working graph and audit history. The inherited saved-host binding
restrictions apply to this first shared editor too; unsaved/revisionable hosts
need their own adapter. The private snapshot is the sole authoritative session:
there is no parallel copy in legacy checklist tempstore or Flexiform HTTP storage.

## Applying templates to an existing entity

`entity_template__apply_to` accepts the same `templates` alternatives and optional
editors. It adds a standard handler-level `context_mapping.target` for the saved
entity to update; each candidate retains its own template parameter mappings:

```yaml
update_record:
  title: Update the related record
  handler: entity_template__apply_to
  handler_configuration:
    context_mapping:
      target: checklist:entity.related_record.0.entity
    templates:
      main:
        template:
          type: embedded
          configuration:
            id: standalone
            label: Update title
            target_entity_type_id: node
            target_entity_bundle: article
            parameters:
              title:
                type: string
                required: true
            components:
              title:
                id: property_context
                path: title.0.value
                context_mapping:
                  value: title
        context_mapping:
          title: checklist:entity.label
```

Use an actual reference field in place of `related_record`. The target may also
be `checklist:entity` itself or an entity outcome from an earlier item. Explicit
list deltas such as `.0.entity` allow an empty reference to resolve without
traversing an absent implicit list item. Missing or unsaved targets do not start
work. Candidates must match the target entity type and bundle, and the executor
must be able to view and update it. Template conditions and components receive
`self` as the target; candidate conditions use ordinary checklist contexts.

The handler clones the target before preparation, detached from its originating
reference or computed context. Pending passes and form actions retain that copy
without saving it. Final completion saves the existing entity and publishes it
as `entity`, so later items use `item:update_record:entity`. It does not create a
replacement record. An editor still uses the transient `entity` binding, with
saving owned by the checklist. This also supports editing the checklist host.

The attempt captures target identity, translation/revision and a fingerprint of
its original values. Resume, editor reads and final save check the live target:
deletion, remapping, intervening edits or revoked access cannot silently redirect
or overwrite the work. A preparation conflict fails the attempt and retains its
private state; an interactive conflict rejects the operation and retains the
last committed editor; reloading the HTML form displays a conflict message
without submission controls. A final-save exception rolls back the entity update and
completion, records failure and retains previously committed state. Recovery
and reset operations remain separate work.

The fingerprint detects stale data across requests; it is not an atomic
compare-and-swap with arbitrary external writers. Entity storage and consumers
still own concurrent-write policy. This slice adds no generic merge/save-policy
framework. Revisionable targets must be the current revision; historical-revision
editing, bulk targets and D7's separate entity-confirmation step are not provided.
The existing checklist **host** restrictions still apply even when the target is
another entity. Use storage on the checklist database connection when relying on
transactional rollback; external effects in save hooks cannot be rolled back.
