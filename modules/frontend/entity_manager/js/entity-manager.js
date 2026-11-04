(function (Drupal, once, drupalSettings) {
  let readerSequence = 0;
  Drupal.behaviors.entityManager = {
    attach(context) {
      once('entity-manager', '[data-entity-manager]', context).forEach((element) => {
        const reader = element.querySelector('.entity-manager__reader');
        const content = reader.querySelector('[data-entity-manager-content]');
        // Cached instances of the same View may repeat server-generated IDs.
        content.id = `entity-manager-reader-client-${++readerSequence}`;
        const status = reader.querySelector('[data-entity-manager-status]');
        const fullPage = reader.querySelector('[data-entity-manager-open]');
        let generation = 0;
        let opener;
        let dirty = false;
        content.addEventListener('input', () => { dirty = true; });
        content.addEventListener('change', () => { dirty = true; });
        const canLeave = () => !dirty || window.confirm(Drupal.t('Discard unsaved changes in this preview?'));
        const clear = () => {
          Drupal.detachBehaviors(content, drupalSettings, 'unload');
          content.replaceChildren();
          dirty = false;
        };
        const close = () => {
          if (!canLeave()) return;
          generation += 1;
          clear();
          reader.hidden = true;
          element.classList.remove('entity-manager--open');
          element.querySelectorAll('.entity-manager__selected').forEach((row) => row.classList.remove('entity-manager__selected'));
          opener?.focus({preventScroll: true});
        };
        reader.querySelector('[data-entity-manager-close]').addEventListener('click', close);
        reader.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') { event.preventDefault(); close(); }
        });
        element.querySelectorAll('tr[data-entity-manager-url]').forEach((row) => {
          const canonical = new URL(row.dataset.entityManagerCanonical, window.location.origin);
          // Preserve ordinary canonical links, modified clicks and new tabs.
          const link = Array.from(row.querySelectorAll('a[href]')).find((a) => a.href === canonical.href);
          if (!link) return;
          link.setAttribute('aria-controls', content.id);
          row.addEventListener('click', (event) => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const control = event.target.closest('a, button, input, select, textarea, label, summary, form');
            if (control && control !== link) return;
            if (window.getSelection()?.toString()) return;
            event.preventDefault();
            if (!canLeave()) return;
            const version = ++generation;
            opener = link;
            clear();
            fullPage.href = canonical.href;
            reader.hidden = false;
            element.classList.add('entity-manager--open');
            element.querySelectorAll('.entity-manager__selected').forEach((r) => r.classList.remove('entity-manager__selected'));
            row.classList.add('entity-manager__selected');
            status.textContent = Drupal.t('Loading @label…', {'@label': row.dataset.entityManagerLabel});
            const ajax = Drupal.ajax({url: row.dataset.entityManagerUrl, httpMethod: 'GET', wrapper: content.id, method: 'html', progress: false});
            const insert = ajax.commands.insert;
            ajax.commands.insert = function (request, response, code) {
              if (version !== generation || !element.isConnected) return;
              return insert.call(this, request, response, code);
            };
            const success = ajax.success;
            ajax.success = function (response, code) {
              if (version !== generation || !element.isConnected) return Promise.resolve();
              return Promise.resolve(success.call(this, response, code)).then(() => {
                if (version !== generation) return;
                status.textContent = '';
                content.querySelector('[data-entity-manager-heading]')?.focus({preventScroll: true});
                if (window.matchMedia('(max-width: 60rem)').matches) {
                  reader.scrollIntoView({block: 'start', behavior: 'smooth'});
                }
              });
            };
            ajax.error = function () {
              if (version !== generation || !element.isConnected) return;
              clear();
              status.textContent = Drupal.t('This record could not be loaded. Check your access or try again.');
            };
            ajax.options.timeout = 30000;
            const complete = ajax.options.complete;
            ajax.options.complete = function (xhr, code) {
              if (code === 'timeout') ajax.error(xhr);
              return complete.call(this, xhr, code);
            };
            ajax.execute().always(() => { Drupal.ajax.instances[ajax.instanceIndex] = null; });
          });
        });
      });
    }
  };
})(Drupal, once, drupalSettings);
