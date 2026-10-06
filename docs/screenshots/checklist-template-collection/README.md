# Collection template screenshots

Real Playwright captures from the local Drupal DDEV preview on 2026-10-06 at
1440 × 1100. Both images were opened and visually inspected before publication.

- `configuration.png`: Document and Reviewer use ordinary context mappings.
  Their sources are collections, so the UI identifies both as iteration inputs.
- `document-reviews.png`: two File entities × two reviewer strings produce four
  independently labelled manual review items.

The Playwright walkthrough also changed the selected template away and back,
verified automatic AJAX rebuilding with mappings retained and the dialog still
open, checked that the fallback button was hidden, and found no JavaScript errors.
The functional browser test separately checks the non-JavaScript fallback.

Fixture job: `document_collection`; local task: `21`. File entities contain harmless
sample text. The screenshot demonstrates expansion, not a new document viewer.
