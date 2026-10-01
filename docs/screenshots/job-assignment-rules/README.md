# Job assignment rules

Real Drupal 10/Claro screenshots captured with Playwright at 1440×1050.
Both screenshots were opened and visually inspected; no mockups are used.

To reproduce, open a job's Assignment rules tab and choose Add assignment rule.
Enter “Urgent work to the task creator”, select `task.creator.0.entity`, choose
Condition string and click Update condition. Enter
`task.title.value matches "/urgent/i"`. `editor.png` shows this actual AJAX-rebuilt
off-canvas form. Add the rule, then add “Other work to the current user”, mapped to
`@user.current_user_context:current_user`, with no condition. `rules.png` shows the
two rules in their evaluation order, still in the shared unsaved job draft.

The browser check then navigated to Settings and back to confirm draft retention,
and asserted that no browser JavaScript errors occurred. Automated functional
tests additionally cover reordering, removal, Save and Discard.
