# Authorization of automatic checklist work

An execution attempt distinguishes the **initiator** (who requested work), the
**executor** (whose permissions and runtime contexts the handler uses), and the
**authorizer** (who approved the delegated job definition). These may be three
different accounts. The attempt and its history retain these identities.

By default an item executes as its initiator. This includes dynamically added
items: copying `execution`, `executor`, or `authorizer` settings onto an item does
not authorize impersonation. There is no client-facing executor override.

## Configuring delegated job work

A user with both job administration access and the restricted **Authorize
delegated checklist execution** permission can select **Execution identity** on
any job checklist handler supporting automatic iterations, including template
handlers that may also request interaction. The policy applies only to automatic
execution; configuration never evaluates a handler's runtime method. The
standard context assignment widget selects the execution user; Typed Data Plus
supplies property traversal, filters and global provider contexts when its
context handler is enabled.

For example, this job item runs as the task assignee:

```yaml
default_checklist:
  prepare:
    label: Prepare the support plan
    handler: your_automatic_handler
    handler_configuration: {}
    execution:
      mode: context
      context_mapping:
        executor: checklist:entity.assignee.entity
```

The selector must resolve to an existing, active user. The initiator still needs
access to the host, checklist field and item. The executor also needs those
permissions and the handler's own operation permissions; delegation does not
bypass access control. Contexts and applicability/actionability gates used by
the automatic runner are evaluated as the executor.

Editing the item only changes the editor's draft. **Save** on the job approves
its saved definition. The job save hooks enforce the restricted permission even
for code that bypasses the form. A configurer without it cannot change any part
of a delegated job, including its checklist, contexts or assignment rules.

The permission deliberately grants broad authority to select execution users.
Grant it only to trusted workflow authors. It is not the selected user's consent
and is not a substitute for an executor's normal access checks.

## Local approval and job versions

Approval lives in the `task_job.execution_authorization` key/value collection,
not exported YAML. Each grant contains a UUID, approval time, exact job config
ID, authorizer and definition fingerprint. The fingerprint covers the entire
job and the transitive configuration dependencies it declares. Referenced
plugins must correctly declare their configuration dependencies; runtime data
and deployed PHP code are not frozen by this fingerprint.

A configuration import always clears local approval for the imported job.
Default configuration installation and other `trustData()` writes do likewise.
An authorized local save is needed before delegated execution can start. This
also means installing an exported job does not silently confer local execution
rights. A same-definition save preserves a still-valid approval; a changed
job, dirty override or replacement approval produces different authority.

Resolution follows the task's actual job reference and pinned version, including
its dirty override. Queued work approved against a clean version cannot silently
continue against a newly introduced dirty version. An attempt keeps its original
grant and execution UID: changing the assignee does not change an existing
attempt's executor. A new attempt resolves the selector again.

Approval is checked before worker execution and before applying a provider's
result. The authorizer must still be active and retain delegation permission;
the executor's active status, current roles and access are also rechecked.
If authority changes during a provider call, its result is rejected and the
attempt records failure requiring reconciliation. Already-performed external
effects cannot be rolled back. Pending work denied before invocation does not
run; the attempt retains its original grant and is not silently rebound to a new
authorization. Reconciliation of such suspended work remains an explicit
operator concern.

## Extension boundary and audit

`checklist.execution_authorizer` dispatches
`ChecklistExecutionAuthorizationEvent` for trusted integration code. A subscriber
may establish a `ChecklistExecutionAuthorization`; otherwise execution is self.
On continuation, the new authorization must exactly match the attempt's stored
executor and provenance. A subscriber must derive authority from a trusted source,
not caller-supplied item settings. Provenance is public audit metadata: never put
credentials, prompt payloads or other secrets in it.

The job subscriber authorizes canonical item slots in the task's saved job,
including expanded decision-template slots. It compares the resolved handler
with that approved definition. A legacy `default_items` checklist override is
not a delegated job source. Arbitrary added item names receive no job grant.

The internal attempt journal persists already-authorized work; directly writing
a row is not an authorization API. Historical self-execution rows without grant
metadata remain executable. Historical rows with different initiator/executor
IDs and no grant cannot establish delegated authority.

History shows the initiating user, execution user and approving user/source.
API history includes the same authorization metadata. Automatic inline work and
worker continuations share this path. Interactive impersonation, executor-consent
flows and general system/service-account policies are outside this slice.

## Verification

`JobExecutionAuthorizationTest` exercises different initiator/executor/authorizer
accounts, pinned identity after reassignment, dirty versions, copied settings,
imports and local reapproval, caller access, revoked accounts/permissions,
referenced configuration changes and revocation during execution.
`TaskJobEditFormTest::testExecutionApprovalOnExplicitSave` covers the standard
context widget, draft/save boundary and denial of unauthorized edits.
`ChecklistAttemptJournalTest::testAuthorizationUpgrade` verifies the schema
upgrade preserves historical rows and events without inventing grants.
