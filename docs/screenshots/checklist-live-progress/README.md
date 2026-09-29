# Live progress browser evidence

Captured with Playwright/Chromium against real Drupal 10 in local DDEV, using Claro
and the production interactive checklist formatter. A temporary admin preview
renders a persisted user checklist containing `input_progress_example`, the
executable fixture documented in the checklist module. Provider work is simulated;
forms, attempts, state, AJAX and polling use production code. This fixture has no
resources, so the checklist occupies the available width.

The same browser page stayed open throughout:

1. `queued.png` — 1440×1000, an explicitly deferred attempt at 0 of 5.
2. A separate Drush request ran one iteration. The browser's GET refresh detected
   the input request and inserted the real action PluginForm without navigation.
   `input-requested.png` shows that desktop state.
3. `mobile-input-requested.png` — resized to 390×844 with the administration tray
   closed. Playwright submitted `LIVE-42` through the displayed form.
4. Another Drush request ran the queued continuation. The next browser refresh
   displayed completion (`mobile-completed.png`) without a page reload.

All four images were visually inspected. The final run observed three successful
refresh requests, no JavaScript errors, no navigation, and no further polling in
the 6.5-second observation period after completion. This proves browser refresh
against separately executed iterations; it does not demonstrate an always-running
worker deployment. The mocked-transport browser test separately covers hidden
pages, in-flight interactions, retry backoff and access failure.
