# Checklist progress screenshots

Captured using Playwright/Chromium against local Drupal 10 in DDEV with Claro, rendering the production `checklist_interactive` field formatter. A temporary admin preview route rendered a persisted user entity with sample checklist configuration and the existing `reader_progress` test handler. These are real Drupal page captures, not mockups; the document extraction data is a fixture, not a running extraction integration.

- `desktop-running.png`: 1440×1000, public progress at 2 of 5, alongside the resource pane.
- `desktop-input-required.png`: 1440×1000, progress at 4 of 5 with Input required, and a completed manual item.
- `mobile-input-required.png`: 390×844, the same state with the administration tray closed and bottom checklist/resources navigation.

All three screenshots were visually inspected. The mobile view was checked for horizontal overflow. No browser JavaScript errors were recorded. These captures demonstrate rendered states after reload; the attempted additional live AJAX transition check timed out and is not claimed as passing evidence. Automated AJAX reconciliation coverage is recorded separately in the PR.
