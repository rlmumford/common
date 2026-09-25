(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.checklistResourcePane = {
    attach: function (context) {
      once('checklist-resource-pane', '.checklist-workspace', context).forEach(function (workspace) {
        workspace.addEventListener('click', function (event) {
          var control = event.target.closest('[data-resource-key]');
          if (!control || !workspace.contains(control)) {
            return;
          }

          var key = control.dataset.resourceKey;
          var panel = Array.from(workspace.querySelectorAll('.checklist-resource-content'))
            .find(function (candidate) { return candidate.dataset.resourceKey === key; });
          if (!panel) {
            return;
          }

          workspace.querySelectorAll('.checklist-resource-content').forEach(function (candidate) {
            candidate.hidden = candidate !== panel;
          });
          workspace.querySelectorAll('.checklist-resource-select').forEach(function (candidate) {
            candidate.setAttribute('aria-pressed', candidate.dataset.resourceKey === key ? 'true' : 'false');
          });
          workspace.querySelectorAll('.checklist-resource-trigger').forEach(function (candidate) {
            candidate.setAttribute('aria-pressed', candidate.dataset.resourceKey === key ? 'true' : 'false');
          });
        });
      });
    }
  };

  Drupal.AjaxCommands.prototype.startNextItem = function (ajax, response, status) {
    if (!response.selector) {
      return false;
    }

    $(response.selector).find("tr[data-ciname=\"" + response.ciname + "\"]").next(".ci-actionable")
      .filter(".ci-actionable.checklist-item-has-form").find(".ci-row-form input.form-checkbox").click();
  };
  Drupal.AjaxCommands.prototype.itemEnsureActionable = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("tr[data-ciname=\"" + response.ciname + "\"]").addClass("ci-actionable");
    }
  };
  Drupal.AjaxCommands.prototype.itemEnsureComplete = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("tr[data-ciname=\"" + response.ciname + "\"]")
        .removeClass('ci-actionable')
        .addClass("ci-complete ci-inactionable");
    }
  };
  Drupal.AjaxCommands.prototype.itemEnsureFailed = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("tr[data-ciname=\"" + response.ciname + "\"]")
        .removeClass('ci-actionable')
        .addClass("ci-failed ci-inactionable");
    }
  };
  Drupal.AjaxCommands.prototype.itemEnsureInProgress = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("tr.ci").removeClass("ci-inprogress");
      $(response.selector).find("tr[data-ciname=\"" + response.ciname + "\"]")
        .addClass("ci-inprogress");
    }
  };
})(jQuery, Drupal, drupalSettings);
