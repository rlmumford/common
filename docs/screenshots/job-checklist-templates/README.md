# Named checklist templates

Actual Drupal 10/Claro screenshots captured with Playwright at 1440px width and
opened for visual inspection. No mock HTML or generated artwork.

To reproduce: edit a job, open Checklist templates, choose Add, add `appointment`
labelled “Appointment preparation”, and add a Simple Checkbox item named
`check_documents`. Switch to Checklist, select Appointment preparation, and Save.
Return to Checklist templates and add `documents`, labelled “Document collection”.
Change its label to “Document review” and click Appointment preparation: the
pending label is retained in the draft and the secondary tab updates.

`templates.png` shows the selected template using the shared checklist table,
with one secondary tab per template and Add. `add.png` shows the Add tab.
`includes.png` shows static inclusion on the main Checklist tab.

The browser check also switched back to verify the label, checked that item tables
are isolated, and discarded the draft: the unsaved template tab disappeared and
the persisted appointment template remained.
