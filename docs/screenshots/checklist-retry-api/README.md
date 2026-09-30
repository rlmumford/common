# API retry evidence

`queued-api-retry.png` is an actual, visually inspected Playwright/Chromium
capture of Drupal 10 DDEV after submitting Resume through the JSON endpoint.
The existing checklist UI shows the queued successor and its history; no new UI
is introduced by this slice.

`http-evidence.json` records the request body, safe response metadata and observed
HTTP status checks. Cookies and CSRF tokens are deliberately omitted. The local
fixture simulates a failed document handler; no external provider was called.

The check reads state/history, acquires a workspace, rejects anonymous and
missing-CSRF mutations, rejects an invalid mode, accepts the retry with 202,
rejects replay with 409, then reads successor history and opens the HTML view.
