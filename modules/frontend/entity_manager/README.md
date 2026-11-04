# Entity Manager

Choose **Entity Manager** as a Views table style to open a selected entity beside
the table. Existing table columns, exposed filters, sorts and pagination remain
Views configuration. The base table must expose a content entity; include a
canonical entity link in a column so keyboard users have the same entry point.

The configured `view_mode` (default: `default`) controls the reader. Entity view
builders and field access remain authoritative. An application can supply a
reader view mode for documents, a checklist/resource view mode for tasks, or any
other entity presentation; this module has no filing or task dependencies.

The preview endpoint resolves the entity type and view mode from enabled View
configuration, checks View access and entity view access, and returns a private,
uncached AJAX response. It does not accept an arbitrary type, view mode, URL or
CSS selector from the browser. List query access remains the consuming entity's
responsibility. A preview ID need not be on the current filtered page, but must
pass the View and entity access checks independently.

Canonical links work without JavaScript and with modified clicks. Actions and
other links inside rows are not intercepted. The reader supports closing,
keyboard focus, unsaved-input confirmation and stale-response suppression;
on narrow screens it stacks above the list. Multiple tables have separate
reader containers. Selecting or closing a row never mutates the entity.

This replaces the table/reader pattern of the platform's Entity Manager, without
porting its organization or workflow assumptions. Existing `task_ui` workspaces
are not switched automatically; their editing/refresh lifecycle needs an explicit
integration before their custom board can be replaced.
