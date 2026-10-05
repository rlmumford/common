# Conditional checklist additions walkthrough

Real screenshots captured with Playwright against the isolated local Drupal
preview site on 2026-10-05, using Claro. No mockups or production data.

The job has a decision, `reference_needed`, and an exposed `reference` template.
Its availability condition is:

```
items.reference_needed.outcomes.decision == "yes"
```

- `availability-config.png`: native condition configuration on the template tab,
  with the addition label and the job's available contexts.
- `availability-before.png`: the decision form before a choice; Add work is absent.
- `availability-after.png`: the compact **Other Actions** dropbutton after the
  decision makes another reference available.
- `additions-menu.png` and `availability-mobile.png`: the direct addition in the
  open menu on desktop and mobile.
- `additions-overflow-menu.png`: a separate seven-choice support job shows four
  direct actions and **Do something else**.
- `additions-overflow-chooser.png` and `additions-overflow-mobile.png`: choosing
  **Do something else** opens the inline chooser in the checklist area.

Playwright exercised opening, cancelling, reopening and submitting the overflow
chooser, then checked the new checklist item appeared. The compact menu and
inline chooser use real Drupal forms, not mocked controls.

Desktop capture: 1440px wide. Playwright reported no page JavaScript errors.
These screenshots show an example without resource tabs, so the checklist uses
all available width.
