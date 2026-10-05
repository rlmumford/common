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
- `availability-after.png`: choosing **Another reference is needed** reveals
  **Request another reference** through the AJAX response, without a page reload.
  Completing **Confirm the support plan** then preserves the selected addition
  and its hidden request UUID, verified by Playwright.
- `availability-mobile.png`: the same available addition at 390px width.

Desktop capture: 1440px wide. Playwright reported no page JavaScript errors.
These screenshots show an example without resource tabs, so the checklist uses
all available width.
