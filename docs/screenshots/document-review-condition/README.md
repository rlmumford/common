# Current-review condition and ordered assignments

Actual Playwright Chromium captures from the local Drupal 10 DDEV site, opened
and visually inspected. Fictional job configuration is used throughout.

- `editor.png`: native document-review condition with the standard Typed Data
  Plus document selector, and no separate fallback assignment setting.
- `unconditional.png`: a final assignment rule with "No condition (always)".
- `rules.png`: the saved draft's conditional rule followed by that default rule.

The browser verified the fallback field is absent, selected the native condition,
verified the Typed Data Plus selector, and saved both rules into the job draft.
