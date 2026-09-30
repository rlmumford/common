# Task dependency editor screenshots

Captured with Playwright against a real Drupal 10.6.17/Claro site in local DDEV,
30 September 2026. These are browser screenshots, not drawings or HTML mockups.
The test-only `task_dependency_test` route hosts Flexiform's `standard` plugin,
with a provided task and the `task_dependencies` component.

Fixture: “Arrange the employment support appointment” waits for “Confirm
employment documents” and has an invalidation subscription to “Withdraw
application”. Both are actual saved task targets. The first dependency follows
explicit replacements; the second does not.

- `flexiform-desktop.png`: 1440px viewport, two-column dependency cards.
- `flexiform-mobile.png`: 390px viewport, stacked controls and no horizontal overflow.

To reproduce on a disposable site, enable `task_dependency_flexiform`, enable
`$settings['extension_discovery_scan_tests']`, then enable `task_dependency_test`.
Create the three tasks and configure the two dependencies on the waiting task.
Open `/task/{id}/dependency-editor` as an account with task editing access.

Browser checks performed: the ordinary task form saved successfully; Add another
item and Remove updated the rows through AJAX; the embedded Flexiform editor
submitted successfully and displayed “Form completed.” Both screenshots were
opened and visually inspected after capture.
