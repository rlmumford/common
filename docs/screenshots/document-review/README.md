# Document review walkthrough

Real Playwright Chromium captures of the local Drupal 10 DDEV walkthrough site,
using fictional document/task data. All three images were opened and visually
inspected. No mock-ups, injected UI or image editing.

- `review.png`: task 22, opened Review Document item with type-owned instructions
  and choices beside its resource. The resource uses the document's configured
  description and file formatter (it is not a PDF viewer).
- `completed.png`: after clicking Approve agreement, persisted individual review
  shown under the completed item, with the actual walkthrough account `admin` in
  the configured `staff` reviewing capacity. The document remains `received`.
- `policy.png`: document-type edit form after saving and reloading review policy.

Browser verification exercised opening the item, submitting approval and saving
and reloading document-type settings. Database inspection confirmed the review,
reviewer, role and source item UUID. Multiple distinct reviewers are covered by
the kernel suite, not simulated in the screenshot.
