# Checklist progress screenshots

Captured using Playwright/Chromium against local Drupal 10 in DDEV with Claro, rendering the production `checklist_interactive` field formatter. A temporary admin preview route rendered a persisted user entity with sample checklist configuration and the existing `reader_progress` test handler. These are real Drupal page captures, not mockups; the document extraction data is a fixture, not a running extraction integration.

- `desktop-running.png`: 1440×1000, public progress at 2 of 5, alongside the resource pane.
- `desktop-input-required.png`: 1440×1000, progress at 4 of 5 with Input required, and a completed manual item.
- `mobile-input-required.png`: 390×844, the same state with the administration tray closed and bottom checklist/resources navigation.

All three screenshots were visually inspected. The mobile view was checked for horizontal overflow. No browser JavaScript errors were recorded. These captures demonstrate rendered states after reload; the attempted additional live AJAX transition check timed out and is not claimed as passing evidence. Automated AJAX reconciliation coverage is recorded separately in the PR.

## Interactive automatic-item example

- `desktop-input-form.png`: 1440×1000, an actual `input_progress_example` iteration has yielded after four documents and embedded its action PluginForm to request a document reference.
- `mobile-input-form.png`: 390×844, the same real form with the administration tray closed. This example has no resources, so the desktop checklist occupies the available width.

Both images were captured from Drupal and visually inspected. Playwright submitted `DOC-42` through the displayed form; the real AJAX response removed the form and changed the progress message back to processing without navigation. No browser JavaScript errors occurred. Running the existing item executor on the queued continuation then completed the attempt and published `reference = DOC-42`. The provider work is simulated by the executable example plugin; the form, AJAX, attempt, state and outcome machinery are production module code.
