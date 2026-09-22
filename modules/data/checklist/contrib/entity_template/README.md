# Checklist Entity Template

Enable `checklist_entity_template` to use the automatic
`entity_template__create` checklist item handler. Install Entity Template's
`1.0.x-dev` branch, which includes typed targets and resumable execution:

```sh
composer config repositories.entity-template vcs https://git.drupalcode.org/project/entity_template.git
composer require drupal/entity_template:1.0.x-dev
```

This optional integration ships inside `rlmumford/checklist`. It does not require
Flexiform. Interactive editing before save and `entity_template__apply_to` are
subsequent slices; this handler prepares and saves automatically.

## Configuration

An embedded template configures one new, fieldable entity. For example, inside a
checklist's `default_items` configuration:

```yaml
create:
  title: Create the follow-up record
  handler: entity_template__create
  handler_configuration:
    template:
      id: standalone
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

The outer `context_mapping` uses the checklist's available contexts and Typed
Data Plus selectors. Components receive the template's parameter contexts and
its live `self` context. Replace the sample selector with a property exposed by
the actual checklist host. Configure other required entity fields as necessary.

Alternatively, choose a specific template from a reusable blueprint:

```yaml
handler_configuration:
  blueprint: follow_up
  template_id: main
  context_mapping:
    title: checklist:entity.label
```

The template must return a single entity. Referenced execution does not run the
other templates in the blueprint, so earlier blueprint results are unavailable.
Builder contexts remain available. Module, bundle, builder, blueprint, component
and condition dependencies are reported for configuration consumers.

This slice provides configuration schema and runtime handling; it does not add
a template selector/editor to the checklist configuration UI.

## Execution and outcomes

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
