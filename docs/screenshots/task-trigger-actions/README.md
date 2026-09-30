# Job trigger action editor

These are real Playwright screenshots from the local Drupal 10 preview running
this branch with Claro. They show the **Triggers** section of the normal job edit
form, after saving and reloading its configuration. Desktop viewport: 1440×1100;
mobile viewport: 390×844. Images capture the full section, including content below
the viewport; no mock HTML or generated artwork is used.

To reproduce on a disposable development site:

1. Enable `task_dependency_job` and open a job's edit form.
2. Add the **Task replaced** trigger and choose **Retarget matching dependencies**.
3. Select **Task resolves**, effect **Activate**, and map Original prerequisite to
   `original`, Replacement prerequisite to `replacement`.
4. Save and reopen the trigger. A second **Manual** trigger demonstrates that the
   two configurations remain independent.

Browser verification switches between creation and retargeting, changes the
watched event and switches it back, submits the mappings, and verifies them on
reload. Screenshots were opened and visually inspected before committing.

- [Desktop](job-trigger-action-desktop.png)
- [Mobile](job-trigger-action-mobile.png)
