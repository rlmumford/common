# Communication review walkthrough

Real Playwright Chromium screenshots from the isolated local Drupal preview,
using Claro at 1440×1000 (desktop) and 390×844 (mobile). Each image was opened
and visually inspected before inclusion in the PR.

1. Configure one communication template with a subject, plain body and a
   confirmation-required operation. The fixture uses the test-only `recorded`
   operation; no real email is sent.
2. Create an active task and process preparation. Advance its generated operation
   to input-required. `confirmation-desktop.png` shows the confirmation form next
   to the saved Draft communication in the 50/50 checklist/resource layout.
3. At mobile width, select Communication in the bottom navigation.
   `message-mobile.png` shows the same saved message without horizontal overflow.
4. Confirm the operation and execute its queued attempt.
   `sent-desktop.png` shows the completed item and the retained resource with Sent
   status. The separate manual appointment item remains incomplete.

These images demonstrate saved entity review and checklist confirmation, not a
mail-client rendering or evidence of external delivery.
