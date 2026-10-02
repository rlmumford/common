# Communication checklist items

Enable `checklist_communication` for runtime handlers and
`checklist_communication_ui` for job configuration controls. Communication must
use `2.0.x-dev`, which includes the merged deferred-operation API. The API routes remain in optional
`checklist_api`; this module does not add routes.

## One authored item, selected template and follow-up

Use `entity_template__create_communication`. It extends the existing template
item, including conditional choices, per-template context mappings, resumable
preparation and the shared Flexiform HTML/API editor. Each candidate has its own
`operation` configuration. Selection captures the template, editor and operation
for that execution; editing job configuration while the form is open does not
change its follow-up.

```yaml
handler: entity_template__create_communication
handler_configuration:
  templates:
    welcome:
      template:
        type: referenced
        configuration:
          blueprint: welcome_email
          template_id: standard
      context_mapping:
        recipient: 'task_context:applicant'
      editor:
        plugin: referenced
        configuration:
          form_id: review_email
      operation:
        id: send
        variant: send_email_mailsystem
        confirm: true
        label: Confirm and send welcome email
```

Both referenced and embedded templates/editors use the existing Entity Template
and Flexiform source/plugin configuration. Every candidate must create a
communication. Templates may supply the editor, and the candidate editor can
override that default as for other template items. Configure recipients, body
and operation inputs in the template or editor.

After automatic preparation (when there is no editor), or after the final editor
submission, the parent saves the communication and adds a required
`communication_operation` item in the same fenced transaction. A failed handoff
rolls back the communication save. The child's machine name derives from the
parent UUID; its communication context maps to `item:<parent-name>:entity`.
The ordinary author does not configure this second item manually. It remains
required until the operation succeeds. The parent's `entity` outcome is the
saved communication; the child's outcomes are `communication` and `succeeded`.

## Operation execution and confirmation

The standalone `communication_operation` handler is also usable on an existing
communication through normal context mapping. Its configuration is `operation`,
`variant`, `confirm` and `context_mapping.communication`.

Only operation/variant implementations supporting Communication's
`DeferredSaveOperationInterface` are supported. This slice includes `send` with
`send_email_mailsystem`. Native resend, Mailgun and operations needing additional
native operation forms are not advertised as supported. An omitted variant
means exactly one applicable variant must exist at execution time.

The worker checks communication view/update access and operation access,
applicability and validation. With `confirm: true`, it waits for input. The HTML
button and API/tool `confirm` operation both submit the advertised `attempt_id`
and `version` into the same audited continuation. Confirmation queues work; it
does not send from the form submission. Repeated or stale confirmations cannot
start a second send.

The provider call runs after the execution claim is durable and outside the
local result transaction. The detached communication result is persisted only
after the executor rechecks its claim and inputs. A reported failure, exception
or uncertain result blocks completion and is not replayed by ordinary queue
redelivery. Review operational history and reconcile with the provider before
using explicit retry. There is no claim of exactly-once external delivery.

## Saved communication review

The preparation item and its operation share a pinned resource tab keyed by the
communication UUID. After the editor saves, the message appears beside the
checklist, including while confirmation is pending. The same resource remains
available after completion. Mobile users open it from the bottom navigation.
Standalone communication-operation items also expose this resource.

The resource reloads the saved communication on each collection and uses its
Drupal `default` view display for status, participants and body fields. Configure
that display to choose the fields and formatters shown. The subject heading has
its own field-access check. Communication view access and normal field access
apply independently of access to the task. Deleted, inaccessible and unsaved
communications have no resource. Reading the pane never saves or runs an operation.
This is a review of saved entity data, not a preview of unsaved editor values or
a guarantee of the transport's final MIME rendering.

## Coverage and walkthrough

Kernel tests cover automatic handoff, conditional choices, editor confirmation,
retained selection, revoked access, reported failure, interrupted delivery and
atomic rollback. Functional tests author the nested configuration through job
drafts, reopen it, complete the real editor and confirm its operation.

Screenshots in `docs/screenshots/communication-checklist` were captured using
Playwright against the isolated Drupal preview site. They use the test-only
`recorded` transport: no real email was sent. Communication's upstream unit tests
exercise the actual Drupal transport with a mocked mail manager.
