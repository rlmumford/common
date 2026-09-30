# Checklist history UI evidence

Captured in Chromium with Playwright against the local Drupal 10 DDEV site on
30 September 2026. These are browser screenshots of rendered Drupal forms and
history panels, not mockups.

The local preview uses a test checklist handler that simulates document work.
Its execution, input form submission, attempt journal, progress refresh and
history controller are the real module implementations. No AI document service
was called for this demonstration.

- `desktop-waiting.png`: the small History link opens a 640-pixel right-hand
  off-canvas panel while the item waits for input. The unsent `HISTORY-42` value
  remains in the form. Closing the panel was checked to preserve that value.
- `desktop-completed.png`: after submitting the form and running the next
  iteration in a separate request, progress polling updates the item to complete.
  Reopening History shows the completion time and transitions through success.
- `mobile-completed.png`: the same completed history at a 390-pixel viewport.
  The panel fills the available width and events become labelled cards; the
  remaining events are available by scrolling.

The browser check also confirmed that opening and closing history did not
navigate away from the checklist and produced no uncaught JavaScript errors.
