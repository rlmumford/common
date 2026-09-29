(function ($, Drupal, drupalSettings) {
  var MAX_MOBILE_RESOURCE_TABS = 4;

  function setActiveResource(workspace, key) {
    var panel = Array.from(workspace.querySelectorAll('.checklist-resource-content'))
      .find(function (candidate) { return candidate.dataset.resourceKey === key; });
    if (!panel) {
      return false;
    }

    panel.open = true;
    workspace.querySelectorAll('.checklist-resource-content').forEach(function (candidate) {
      candidate.dataset.mobileSelected = candidate === panel ? 'true' : 'false';
    });
    return true;
  }

  function buildMobileNavigation(workspace) {
    var navigation = workspace.querySelector('.checklist-workspace-mobile-navigation');
    var panels = Array.from(workspace.querySelectorAll('.checklist-resource-content'));
    if (!navigation) {
      return;
    }

    navigation.querySelectorAll('[data-mobile-generated]').forEach(function (button) {
      button.remove();
    });
    if (!panels.length) {
      navigation.hidden = true;
      workspace.classList.remove('checklist-workspace--mobile-navigation');
      delete workspace.dataset.checklistWorkspaceView;
      return;
    }

    if (workspace.dataset.checklistResourceKey) {
      setActiveResource(workspace, workspace.dataset.checklistResourceKey);
      delete workspace.dataset.checklistResourceKey;
    }
    navigation.hidden = false;
    workspace.classList.add('checklist-workspace--mobile-navigation');
    var activeItem = workspace.querySelector('.ci-inprogress[data-ciname]');
    var activeName = activeItem ? activeItem.dataset.ciname : null;
    var promoted = panels.filter(function (panel) {
      var owners = (panel.dataset.resourceOwners || '').split(/\s+/);
      return panel.dataset.resourcePinned === 'true' || (activeName && owners.indexOf(activeName) !== -1);
    }).slice(0, MAX_MOBILE_RESOURCE_TABS);
    panels.forEach(function (panel) {
      panel.dataset.mobilePromoted = promoted.indexOf(panel) !== -1 ? 'true' : 'false';
    });
    var promotedKeys = promoted.map(function (panel) { return panel.dataset.resourceKey; });

    promoted.forEach(function (panel) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'checklist-workspace-mobile-tab';
      button.textContent = panel.dataset.resourceLabel || panel.dataset.resourceKey;
      button.dataset.checklistWorkspaceView = 'resource';
      button.dataset.resourceKey = panel.dataset.resourceKey;
      button.dataset.resourceIcon = panel.dataset.resourceIcon;
      button.dataset.mobileGenerated = 'true';
      button.setAttribute('aria-controls', panel.id);
      button.setAttribute('aria-pressed', 'false');
      navigation.appendChild(button);
    });

    if (panels.length > promotedKeys.length) {
      var more = document.createElement('button');
      more.type = 'button';
      more.className = 'checklist-workspace-mobile-tab checklist-workspace-mobile-tab--resources';
      more.textContent = Drupal.t('Resources');
      more.dataset.checklistWorkspaceView = 'resources';
      more.dataset.mobileGenerated = 'true';
      more.setAttribute('aria-controls', workspace.querySelector('.checklist-resource-pane').id);
      more.setAttribute('aria-pressed', 'false');
      navigation.appendChild(more);
    }

    var currentView = workspace.dataset.checklistWorkspaceView;
    if (currentView && currentView.indexOf('resource:') === 0) {
      var currentKey = currentView.substring('resource:'.length);
      if (!setActiveResource(workspace, currentKey)) {
        workspace.dataset.checklistWorkspaceView = 'checklist';
      }
      else if (promotedKeys.indexOf(currentKey) === -1) {
        workspace.dataset.checklistWorkspaceView = 'resources';
      }
    }
    else if (currentView !== 'resources') {
      workspace.dataset.checklistWorkspaceView = 'checklist';
    }
    else {
      var otherPanels = panels.filter(function (panel) {
        return promotedKeys.indexOf(panel.dataset.resourceKey) === -1;
      });
      var firstOther = otherPanels.find(function (panel) { return panel.open; }) || otherPanels[0];
      if (firstOther) {
        setActiveResource(workspace, firstOther.dataset.resourceKey);
      }
      else {
        workspace.dataset.checklistWorkspaceView = 'checklist';
      }
    }
    currentView = workspace.dataset.checklistWorkspaceView;
    navigation.querySelectorAll('.checklist-workspace-mobile-tab').forEach(function (button) {
      var selected = button.dataset.checklistWorkspaceView === currentView;
      if (button.dataset.resourceKey) {
        selected = currentView === 'resource:' + button.dataset.resourceKey;
      }
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    // Keep the computed collection available to the click handler without
    // repeating resource ownership and pinning checks for every interaction.
    workspace._checklistPromotedResourceKeys = promotedKeys;
  }

  Drupal.behaviors.checklistResourcePane = {
    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      var panes = context.matches && context.matches('.checklist-resource-pane')
        ? [context] : context.querySelectorAll('.checklist-resource-pane');
      panes.forEach(function (pane) {
        var workspace = pane.closest('.checklist-workspace');
        var selected = pane.querySelector('details[open][data-resource-key]');
        if (workspace) {
          if (selected) {
            workspace.dataset.checklistResourceKey = selected.dataset.resourceKey;
          }
          else {
            delete workspace.dataset.checklistResourceKey;
          }
        }
      });
    },
    attach: function (context) {
      once('checklist-resource-pane', '.checklist-workspace', context).forEach(function (workspace) {
        workspace.addEventListener('click', function (event) {
          var viewControl = event.target.closest('.checklist-workspace-mobile-tab');
          if (viewControl && workspace.contains(viewControl)) {
            var view = viewControl.dataset.checklistWorkspaceView;
            if (view === 'checklist') {
              workspace.dataset.checklistWorkspaceView = 'checklist';
            }
            else if (view === 'resources') {
              workspace.dataset.checklistWorkspaceView = 'resources';
              var promoted = workspace._checklistPromotedResourceKeys || [];
              var firstOther = Array.from(workspace.querySelectorAll('.checklist-resource-content'))
                .find(function (panel) { return promoted.indexOf(panel.dataset.resourceKey) === -1; });
              var firstPanel = firstOther || workspace.querySelector('.checklist-resource-content');
              if (firstPanel) {
                setActiveResource(workspace, firstPanel.dataset.resourceKey);
              }
            }
            else if (view === 'resource') {
              workspace.dataset.checklistWorkspaceView = 'resource:' + viewControl.dataset.resourceKey;
              setActiveResource(workspace, viewControl.dataset.resourceKey);
            }
            workspace.querySelectorAll('.checklist-workspace-mobile-tab').forEach(function (button) {
              var selected = button.dataset.checklistWorkspaceView === workspace.dataset.checklistWorkspaceView;
              if (button.dataset.resourceKey) {
                selected = workspace.dataset.checklistWorkspaceView === 'resource:' + button.dataset.resourceKey;
              }
              button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
          }
        });
      });

      var workspace = context.matches && context.matches('.checklist-workspace') ? context : context.closest && context.closest('.checklist-workspace');
      (workspace ? [workspace] : context.querySelectorAll ? context.querySelectorAll('.checklist-workspace') : [])
        .forEach(buildMobileNavigation);
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
