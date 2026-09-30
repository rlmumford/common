/**
 * @file
 * Retains main-form edits before opening a nested configuration dialog.
 */
(function (Drupal, once, $) {
  Drupal.behaviors.taskJobEditor = {
    attach(context) {
      once('task-job-editor', '.task-job-editor', context).forEach((form) => {
        let pending;
        // Capture before Drupal's link AJAX handler opens the dialog. The form
        // request validates and stores this tab before the dialog reads its job.
        form.addEventListener('click', (event) => {
          const link = event.target.closest('a.use-ajax');
          if (!link || !form.contains(link)) return;
          event.preventDefault();
          event.stopImmediatePropagation();
          if (pending) return;
          pending = {
            url: link.href,
            dialogType: link.dataset.dialogType || 'dialog',
            dialogRenderer: link.dataset.dialogRenderer || 'off_canvas',
            dialog: JSON.parse(link.dataset.dialogOptions || '{}'),
          };
          $(form.querySelector('.task-job-open-dialog')).trigger('mousedown');
        }, true);
        $(form).on('taskJobDraftSaved', () => {
          const options = pending;
          pending = null;
          if (options) Drupal.ajax(options).execute();
        });
        // A failed request should leave the original link usable for retry.
        $(document).off('ajaxError.taskJobEditor').on('ajaxError.taskJobEditor', () => { pending = null; });
      });
    },
  };
})(Drupal, once, jQuery);
