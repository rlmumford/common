(function ($, Drupal) {
  const refreshers = new WeakMap();
  const interval = 5000;

  function watch(workspace) {
    let timer;
    let pending = false;
    let stopped = false;
    let generation = 0;
    let requestedGeneration;
    let delay = interval;
    const ajax = Drupal.ajax({
      url: workspace.dataset.checklistRefreshUrl,
      httpMethod: 'GET',
      progress: false,
    });
    const success = ajax.success;
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    status.hidden = true;
    workspace.appendChild(status);

    function schedule() {
      clearTimeout(timer);
      if (!stopped && !pending && workspace.isConnected && workspace.querySelector('[data-refresh-progress="true"]')) {
        timer = setTimeout(refresh, delay);
      }
    }

    function interacted() {
      // A response requested before this interaction may now be stale.
      generation++;
    }

    ajax.success = function (response, code) {
      if (stopped || !workspace.isConnected || requestedGeneration !== generation) {
        return Promise.resolve();
      }
      status.hidden = true;
      delay = interval;
      return success.call(this, response, code);
    };
    ajax.error = function (xhr) {
      // Avoid a recurring modal error when a background refresh fails.
      stopped = [403, 404, 409].includes(xhr.status);
      delay = Math.min(delay * 2, 60000);
      status.textContent = stopped
        ? Drupal.t('Live updates stopped. Reload this page to check access and current work.')
        : Drupal.t('Live updates are temporarily unavailable. Retrying shortly.');
      status.hidden = false;
    };
    ajax.options.timeout = 30000;
    // Core calls ajax.error for error/parsererror, but not timeout responses.
    const complete = ajax.options.complete;
    ajax.options.complete = function (xhr, code) {
      if (code === 'timeout') {
        ajax.error(xhr);
      }
      return complete.call(this, xhr, code);
    };

    function refresh() {
      if (!workspace.isConnected || stopped || !workspace.querySelector('[data-refresh-progress="true"]')) {
        return;
      }
      // Never compete with a form submission or another AJAX command sequence.
      if (document.hidden || $.active || ajax.ajaxing) {
        schedule();
        return;
      }
      pending = true;
      requestedGeneration = generation;
      ajax.execute().always(function () {
        pending = false;
        schedule();
      });
    }

    ['input', 'change', 'click', 'submit'].forEach(event => workspace.addEventListener(event, interacted, true));
    const watcher = {
      schedule,
      destroy() {
        stopped = true;
        clearTimeout(timer);
        ['input', 'change', 'click', 'submit'].forEach(event => workspace.removeEventListener(event, interacted, true));
        Drupal.ajax.instances[ajax.instanceIndex] = null;
        status.remove();
        refreshers.delete(workspace);
      },
    };
    refreshers.set(workspace, watcher);
    return watcher;
  }

  Drupal.behaviors.checklistProgressRefresh = {
    attach(context) {
      const workspaces = new Set(context.querySelectorAll ? context.querySelectorAll('[data-checklist-refresh-url]') : []);
      const parent = context.closest && context.closest('[data-checklist-refresh-url]');
      if (parent) {
        workspaces.add(parent);
      }
      workspaces.forEach(workspace => (refreshers.get(workspace) || watch(workspace)).schedule());
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      const workspaces = context.querySelectorAll ? Array.from(context.querySelectorAll('[data-checklist-refresh-url]')) : [];
      if (context.matches && context.matches('[data-checklist-refresh-url]')) {
        workspaces.push(context);
      }
      workspaces.forEach(workspace => refreshers.get(workspace)?.destroy());
    },
  };
})(jQuery, Drupal);
