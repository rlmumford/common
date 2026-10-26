# Task workspace

`task_ui` is an optional UI for the existing task/checklist system. It provides `/tasks` with My work, Unassigned, Team work and History queues, title search, separate due dates and external deadlines, and a shared `/task/{task}` workspace. It does not record time, create a second workflow, or alter task access policy.

Queries use task query access and paginate 25 records; entity access is checked again before rendering. Pages select newest records first and group that page using live `task.readiness` results. This intentionally does not claim to globally sort a dynamic readiness calculation. Related records must pass their own view access. Readiness diagnostics containing inaccessible entity IDs are not displayed.

The task page renders the existing interactive checklist, including its document/resource pane and completion gates. Read-only viewers see checklist state without mutation controls. Due dates and court/external deadlines remain separate fields. No generic status editor is added.

Applications extend presentation with standard Drupal alter hooks:

- `hook_task_ui_context_fields_alter(&$fields)`: opt single-valued entity-reference fields into list and task-summary columns (`field_name => translated label`).
- `hook_task_ui_navigation_alter(&$links)`: add navigation links.
- `hook_task_ui_task_actions_alter(&$actions, $task)`: add authorized render-array actions.
- `hook_task_ui_assignee_alter(&$label, $task)`: apply application-specific staff-label visibility.

Organization membership and claim/reassignment actions belong to CounselKit's optional `ck_organization_task`. CMS owns its `/user` landing integration and filing/case fields. Installing the base task package alone does not enable these routes.
