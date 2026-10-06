# Template inputs and addition buttons

Real Playwright captures of the local Drupal Claro editor, 6 October 2026.
The fixture job declares no job-level inputs. Its Reference check template
owns a required string input named `subject`, labelled “Reference subject”.

- `addition-context-config.png`: compact Template contexts and Exposure tables.
- `context-dialog.png`: actual Edit input off-canvas form. Playwright also
  submitted Update input and verified return to the template editor.
- `addition-exposures.png`: actual Edit addition button off-canvas, including its
  mapping and availability condition. Playwright verified condition rebuilds
  preserve the mapping and Update returns to the table with Conditional shown.
- `addition-context-mobile.png`: the same editor at a 390px viewport, using core
  responsive-table priorities to keep labels and Edit visible.

Desktop viewport: 1440 × 1100. Captures were opened and visually inspected.
These are configuration screenshots; runtime mapping and independent receipt
behavior are covered by the kernel tests.
