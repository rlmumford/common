# Dedicated Flexiform checklist item

Captured with Playwright against the local Drupal 10 DDEV preview on 2026-10-01.
Both images were opened and visually inspected. They are browser screenshots of
the implementation, not mockups.

- `editing.png`: an automatically opened referenced Flexiform edits a supplied
  applicant entity and a transient typed review note. The following confirmation
  remains blocked.
- `completed.png`: Submit saves the form, completes the item and unlocks the
  separate confirmation item without leaving the task page.

The fixture uses synthetic applicant data. It has no resources configured, so
there is no right-hand resource pane. The native context-assignment extension is
enabled; no CSS was injected by the screenshot script.
