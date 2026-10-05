# Drupal mail checklist walkthrough

Actual Playwright Chromium screenshot, captured at 1440×1000 on the isolated
Drupal preview site and opened for visual inspection.

The job uses `entity_template__create_communication` with the real `send`
operation and `send_email_mailsystem` variant, with confirmation enabled.
Synthetic saved participants provide From, To and CC. Drupal's mail backend is
`test_mail_collector`: no email leaves the preview environment.

The walkthrough prepared the communication, displayed its resource alongside the
confirmation form, submitted confirmation, and ran the queued worker. The image
shows the completed operation, Sent status and saved participants/message. The
separate appointment item remains incomplete. The collector records the To/CC,
subject and body; kernel tests assert routing and the single sent event.

An earlier walkthrough exposed a computed participants cardinality defect after
the transport call. That attempt was not replayed. Communication !13 fixes the
definition; this screenshot is from a fresh task after the fix, which completed.
