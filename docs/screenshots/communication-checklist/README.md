# Communication checklist walkthrough

Captured with Playwright on 2026-10-02 against the isolated Drupal 10.6 DDEV preview
site. Every PNG was opened and inspected. These are browser captures of working
Drupal forms, not mockups. Synthetic task: “Welcome Alex Morgan”.

- `configuration.png`: a scrolled viewport of the first candidate's editor and
  operation controls; unrelated configuration sections were collapsed normally.
- `editor.png`: template selection has opened its embedded subject editor.
- `confirmation.png`: saving produced required follow-up work; after its worker
  pass the item asks for confirmation in the checklist area.
- `confirmation-mobile.png`: the same form at 390px width, with no horizontal
  overflow.
- `complete.png`: submitting confirmation and running the resulting queued attempt
  completed the operation. A separate manual item still blocks task resolution.

The installed test-only `recorded` operation captures calls and changes local
status. No real message was sent. Actual Drupal transport behaviour is tested
upstream with a mocked mail manager. For reproduction, enable
`checklist_communication_ui` and `checklist_communication_test`, author a candidate
with `recorded`, an embedded editor bound to `entity`, and confirmation enabled.
Choose its template, submit the editor, run the task/iteration worker, confirm,
and run the queued iteration. Do not enable this test transport on a real site.
