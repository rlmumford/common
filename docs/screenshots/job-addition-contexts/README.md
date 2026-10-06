# Addition context mapping editor

Real Playwright screenshots from the isolated local Drupal preview on 2026-10-06,
using Claro. Desktop viewport 1440px; mobile viewport 390px. Captures were inspected.

The reference-check template maps the job's string **Reference subject** input to
`checklist:entity.title.value`, and its user **Contact** input to
`checklist:entity.creator.entity`. Both use the native Typed Data Plus autocomplete
widget. The availability condition below still uses the containing task's decision.

The preview includes the companion Typed Data Plus entity-selector fix: without
it, the condition editor can run entity validators against an empty entity context
and fail. Browser and kernel tests cover the behavior separately from these images.
