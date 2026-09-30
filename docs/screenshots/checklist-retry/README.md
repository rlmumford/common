# Checklist retry and history browser captures

Captured from the running Drupal 10 DDEV site with Playwright/Chromium. These are
actual screenshots, not mockups. The local document handler simulates work and
failure; the formatter, retry form, executor, journal, input form and resource
pane are production module code. No external document or AI service was called.

- `desktop-confirmation.png`: retry expands in the failed item on the left;
  its history remains visible in the resource pane on the right.
- `mobile-confirmation.png`: the same inline confirmation under Checklist.
- `desktop-queued.png`: submission replaces the controls with queued progress.
- `desktop-completed.png`: the completed successor and its history resource.
- `mobile-history.png`: history uses the existing mobile Resources navigation.

The browser checks exercised Cancel/reopen, invalid CSRF rejection, Resume,
background iteration, requested input, completion and predecessor/latest-history
navigation. A separate concurrent-attempt check verifies that a stale form shows
an explanation without retrying its successor. No off-canvas is involved.

The committed `tests/browser/resource-refresh.cjs` also verifies that opening and
paging history preserves other resource inputs, that history survives a pane
refresh, and that row refreshes preserve an inline retry choice only while the
same failed attempt remains current.
