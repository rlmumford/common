# Checklist UI browser evidence

Captured with Playwright/Chromium against Drupal 10 in local DDEV, using Claro, at desktop 1440×1000 and mobile 390×844.

These captures render a real persisted user entity's checklist field using `checklist_interactive`, including the production manual-item row forms, decision action form, completion form, resource collector and resource pane builder. A temporary test route rendered the field; a test event subscriber supplied four sample expense-related resources. This is a checklist formatter test, not a deployed task page or a finished expense integration. The site cookie banner was removed for capture, and the administration tray was closed on mobile.

- `desktop.png`: equal-width checklist and resources, real decision form expanded, native details summaries styled as resource tabs.
- `checklist.png`: mobile checklist with consistent bottom navigation and a completed manual item.
- `decision.png`: mobile decision form, including the real submit buttons.
- `promoted-resource.png`: Expenses selected directly from the bottom navigation.
- `resource-collection.png`: remaining resources as an accordion, excluding the resources already promoted to bottom tabs.

Browser checks exercised manual completion, decision submission and persistence after reload, exclusive desktop resource selection, mobile resource switching and accordion expansion. Equal desktop column widths and absence of “Open resource” controls were asserted. No browser JavaScript errors were recorded. All five images were visually inspected.
