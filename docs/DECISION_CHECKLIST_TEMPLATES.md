# Decision-driven checklist templates

A decision choice can activate a named template from the task's versioned job.
Configure the reusable items under **Checklist templates**, then select the
**Checklist template** on the relevant choice in the decision's configuration
panel. This uses the same working job draft as the other tabs; only **Save**
commits it. A template referenced by a decision cannot be deleted until its
references are removed.

```yaml
default_checklist:
  review:
    label: Review the evidence
    handler: decision
    handler_configuration:
      question: Do we have enough evidence?
      options:
        more:
          label: Further documents needed
          template: documents
          context_mapping:
            'task_context:applicant': 'checklist:entity.creator.entity'
        ready:
          label: Evidence is complete
checklist_templates:
  documents:
    label: Collect further evidence
    items:
      request:
        label: Request the missing documents
        handler: simply_checkable
        handler_configuration: {}
      check:
        label: Check the new evidence
        handler: decision
        handler_configuration:
          question: Does the evidence meet the requirements?
          options:
            accepted:
              label: Accept evidence
            clarify:
              label: Clarification needed
              require_reason: true
```

The optional mapping in this example assumes the job declares an `applicant`
context. Leave mappings empty to inherit the job's contexts. Branch mappings
use the shared Typed Data Plus context handler, so referenced properties,
filters and global provider selectors work the same way as elsewhere. The
context-assignment submodule supplies its normal autocomplete widget when
installed. The task hosting the checklist remains the fixed host context;
branch mappings change only declared job inputs.

## Identity, activation and history

All reachable branch **definitions** are expanded up front so expected outcomes
are available while configuring subsequent items. This does not execute or save
all those items. Unselected, unsaved branches are absent from the interactive
checklist. Completing a decision activates its selected branch immediately,
through the existing form or `choose` action operation.

Names include the parent, choice, template and local item name:

```
review__more__documents__request
review__more__documents__check
```

The same template can be used by multiple choices or decisions without sharing
completed work. Re-entering the same branch uses the same names and persisted
item UUIDs. Expansions are limited to 1,000 items, 16 template levels and the
existing 255-character item-name limit. Collisions, missing references and
recursive inclusion are configuration errors, never silent partial completion.

Generated items carry a `derivation` map containing their decision requirements
and context scopes. Every enclosing decision must be complete and select the
matching choice. A branch no longer in the current definition catalog is also
inactive. The existing applicability, actionability and requiredness conditions
still apply within an active branch.

If a workflow explicitly revises a decision, work in the previous branch is
retained: status, completion timestamp, outcomes, working state and attempt
history are not reset or deleted. Saved inactive work remains visible for
history, with actions disabled. It contributes no runtime outcome values or
resources and does not prevent checklist completion. This slice does not add
a UI for reopening completed decisions or an orphan-deletion workflow.

## Local and qualified outcome contexts

Inside the `documents` template, sibling outcomes use their local names:

- Context mapping: `item:check:decision`.
- Condition string: `items.check.outcomes.decision == "accepted"`.
- `__parent` refers to the decision that instantiated this template.

Outside the template, use the qualified identity, for example:

- `item:review__more__documents__check:decision`.
- `items.review__more__documents__check.outcomes.decision == "accepted"`.

Nested templates get nested scopes. Branch inputs are resolved against the
enclosing scope before local aliases are applied. Inactive outcome definitions
remain discoverable, but their runtime values are absent; required inputs wait
rather than receiving outcomes from a previously selected branch.

## Execution and versioning

The normal processor, action operation dispatcher and iteration workers apply
branch activation before acting. A branch of synchronous automatic items can
finish in one checklist pass, including passing outcomes between its items.
Long-running items continue through the existing attempt and scheduling system.
No new queue or execution facade is introduced.

Template definitions come from the job version selected by the task, including
that version's dirty overlay. Persisted unfinished items read their labels and
handler configuration from that definition on reload, preserving identity, state
and history. Completed items retain their recorded configuration. See
[LIVE_JOB_CHECKLIST_CONFIGURATION.md](LIVE_JOB_CHECKLIST_CONFIGURATION.md).

Run `drush updatedb` to install the new provenance field on existing sites.
`DecisionChecklistTemplatesTest` covers API choice, scoped and later outcomes,
branch switching, reload identity, context mapping, nested branches, synchronous
automation and invalid template references. `TaskJobEditFormTest` covers draft
navigation, explicit Save and referenced-template removal protection.
