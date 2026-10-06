# Template inputs and addition buttons

Real Playwright captures of the local Drupal Claro editor, 6 October 2026.
The fixture job declares no job-level inputs. Its Reference check template
owns a required string input named `subject`, labelled “Reference subject”.

- `addition-context-config.png`: compact template input table; Exposure collapsed using its normal control.
- `context-dialog.png`: actual Edit input off-canvas form. Playwright also
  submitted Update input and verified return to the template editor.
- `addition-exposures.png`: actual Exposure element capture showing two buttons
  mapping the same input to the task title and description respectively.
- `addition-context-mobile.png`: the same editor at a 390px viewport.

Desktop viewport: 1440 × 1100. Captures were opened and visually inspected.
These are configuration screenshots; runtime mapping and independent receipt
behavior are covered by the kernel tests.
