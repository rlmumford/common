# Document review history walkthrough

Real Chromium screenshots captured with Playwright on the local DDEV Drupal 10
preview, 7 October 2026. All three images were opened and visually inspected.
The document, people, decisions and reasons are fictional local fixtures.

- `document-link.png`: the small Review history link below the document's Required
  reviews summary; the checklist review form is open with unsaved notes.
- `history-desktop.png`: clicking the link opens the review trail in the right-hand
  resource pane and preserves those notes. Recorded role and decision labels are
  retained, including the older `staff` label. Current/earlier version attribution
  is independent of the decision. Entries are ordered by recording sequence;
  the earlier-version fixture was inserted after the existing legacy review.
- `history-mobile.png`: the same resource at 390px wide, with bottom navigation
  between Checklist, Document and Review history.

The walkthrough asserted the textarea still contained `Working review notes`
after opening history, and the mobile page did not overflow horizontally. It did
not submit that review or alter task completion. Desktop viewport: 1440×1000;
mobile viewport: 390×844; images capture the full page.
