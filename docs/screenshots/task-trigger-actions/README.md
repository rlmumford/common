# Tabbed job configuration

These are real Playwright screenshots from the local Drupal 10 preview running
this branch with Claro, using Drupal local-task links and distinct edit routes.
They show the normal job editor and its actual off-canvas
item form. Desktop viewport: 1440×1050; mobile viewport: 390×844. Full-page captures
include content below the viewport. No mock HTML or generated artwork is used.
All four images were opened and visually inspected before committing.

To reproduce on a disposable development site:

1. Enable `task_dependency_job` and open a job's edit form.
2. Add two Simple Checkbox checklist items and Save the job.
3. Select **Triggers**, add **Task replaced**, and choose **Retarget matching
   dependencies**. Select **Task resolves**, effect **Activate**, and map Original
   prerequisite to `original`, Replacement prerequisite to `replacement`.
4. Save. Switch between Checklist, Triggers, Contexts, Assignment rules and
   Settings. A second Manual trigger demonstrates independent configuration.
5. On Checklist, use an item's configure operation to open the real off-canvas
   form. Update modifies the draft; Save on the parent editor persists the job.

Playwright also verified retention of settings through tab changes, Discard,
pending trigger fields retained before a nested condition dialog, validation
blocking tab navigation until corrected (including after AJAX replaces the form),
and mobile width. Functional tests separately verify persisted configuration, explicit
version saves, external-change conflicts and draft ownership.

- [Checklist tab](job-editor-checklist.png)
- [Checklist item dialog](job-editor-dialog.png)
- [Triggers tab — desktop](job-trigger-action-desktop.png)
- [Triggers tab — mobile](job-trigger-action-mobile.png)
