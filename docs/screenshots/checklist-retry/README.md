# Retry UI browser evidence

Captured with Playwright/Chromium against local Drupal 10 DDEV on 30 September
2026. These are actual rendered UI screenshots, inspected after capture.

The preview handler simulates document work and returns a failed iteration with
retained progress. It does not call an AI/document service. The executor, attempt
journal, Form API confirmation, queue preparation, input submission and progress
refresh are the real module implementations.

- `desktop-confirmation.png`: failed item with a separate small History link;
  Retry opens a right-aligned 520-pixel panel with explicit state-handling choices.
- `mobile-confirmation.png`: the same confirmation fills a 390-pixel viewport.
- `desktop-queued.png`: submission closes the panel and refreshes the checklist;
  saved progress is retained while the new attempt waits for a worker.
- `desktop-completed.png`: after one iteration in a separate request, input through
  the existing action form, and another iteration, polling shows completion. The
  history panel identifies the Resume attempt and links back to its predecessor.

The browser exercise also rejected a deliberately invalid form token. A separate
run submitted a stale confirmation after another request had already retried the
item; the panel replaced its controls with an explanation instead of retrying the
newer work. Neither run produced an uncaught JavaScript error.
