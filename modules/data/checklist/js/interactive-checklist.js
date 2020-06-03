(function ($, Drupal, drupalSettings) {
  Drupal.AjaxCommands.prototype.startNextItem = function (ajax, response, status) {
    if (!response.selector) {
      return false;
    }

    $(response.selector).find("li[data-ciname=\""+response.ciname+"\"]").next(".ci-actionable")
      .filter(".ci-actionable.checklist-item-has-form").find(".ci-row-form input.form-checkbox").click();
  };
  Drupal.AjaxCommands.prototype.itemEnsureActionable = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("li[data-ciname=\""+response.ciname+"\"]").addClass("ci-actionable");
    }
  };
  Drupal.AjaxCommands.prototype.itemEnsureComplete = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("li[data-ciname=\""+response.ciname+"\"]")
        .removeClass('ci-actionable')
        .addClass("ci-complete ci-inactionable");
    }
  };
  Drupal.AjaxCommands.prototype.itemEnsureFailed = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("li[data-ciname=\""+response.ciname+"\"]")
        .removeClass('ci-actionable')
        .addClass("ci-failed ci-inactionable");
    }
  };
  Drupal.AjaxCommands.prototype.itemEnsureInProgress = function (ajax, response, status) {
    if (response.selector) {
      $(response.selector).find("li.ci").removeClass("ci-inprogress");
      $(response.selector).find("li[data-ciname=\""+response.ciname+"\"]")
        .addClass("ci-inprogress");
    }
  };
})(jQuery, Drupal, drupalSettings);
