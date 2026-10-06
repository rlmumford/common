# Named review walkthrough

Actual Playwright Chromium captures from the local Drupal 10 DDEV site, using
fictional walkthrough data. Each screenshot was opened and visually inspected.
No generated mock-ups or injected UI.

- `definitions.png`: named staff, debtor and creditor requirements on a type.
- `staff.png`: saved/reloaded staff permission, prompt, allowed decisions and
  analysis schema. Prompt configuration does not start an AI run.
- `debtor.png`: the real Typed Data Plus selector mapping a required reviewer.
  `debtor` and `creditor` fields are fixture-only user-reference fields on this
  document bundle; the module does not impose those relationships on consumers.
- `task.png`: a real opened Review Document item shows the named staff requirement
  and its four type-owned decisions beside the configured document resource.

The browser exercised configuration save/reload and opening the action form.
Kernel tests exercise distinct people, wrong-person rejection, partial/incomplete,
current-version evidence and analysis validation. Functional tests also exercise
adding a definition, rebuilding context choices and preserving them on type save.
