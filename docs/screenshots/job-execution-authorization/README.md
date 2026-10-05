# Delegated job execution walkthrough

Real Playwright screenshots from the isolated local Drupal site, captured on
5 October 2026 and visually inspected before adding them to the PR.

- `configuration.png`: the actual checklist-item off-canvas configuration form,
  selecting the task assignee with the Typed Data Plus context mapping widget.
- `history.png`: the completed item's history opened in the resource pane.
  Alex initiated the attempt, Sam executed it, and Morgan approved the job.

The synthetic walkthrough uses the `checklist_state_test` single-step handler,
which stores a typed outcome without sending mail or making external calls.
The job and task were seeded locally; the attempt was executed through
`checklist.item_executor->submit()`. The pages, configuration widget and AJAX
history/resource pane are the real module UI, not mockups.
