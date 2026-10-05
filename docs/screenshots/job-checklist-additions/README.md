# Checklist additions walkthrough

Actual Chromium screenshots captured with Playwright against the isolated local
Drupal 10 site, using Claro. Captured after adding the `reference` template to
task 14 through the Add work form, then opening its decision item through the
checkbox/start control. All three images were opened and visually inspected.

- `configuration.png`: the job template's explicit staff-addition option and its
  two configured items in the tabbed job editor (1440px viewport).
- `added-work.png`: original task item plus the newly added reference check,
  including its working decision action form and Add work control.
- `mobile.png`: the same working form at 390px viewport width. This fixture has no
  resource tabs, so the checklist takes the available width.

The fixture uses the built-in `simply_checkable` and `decision` handlers; no mail,
external provider or production data was involved. These are browser captures,
not mock-ups or illustrations. The regression suites create their own independent
fixtures and additionally verify authoring drafts, permissions, HTTP replay,
outcome isolation and job version behavior.
