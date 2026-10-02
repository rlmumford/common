// DOM regression coverage for the production Drupal resource-pane behavior.
// Run with node; install Playwright or set PLAYWRIGHT_MODULE to its module path.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent('<div class="checklist-workspace"><nav class="checklist-workspace-mobile-navigation"><button class="checklist-workspace-mobile-tab" data-checklist-workspace-view="checklist">Checklist</button></nav><div class="checklist-resource-pane" id="resources"></div></div>');
    await page.evaluate(() => {
      window.Drupal = { detachBehaviors: () => {}, attachBehaviors: () => {}, behaviors: {}, t: text => text, AjaxCommands: function () {} };
      window.drupalSettings = {};
      window.jQuery = {};
      const seen = new WeakSet();
      window.once = (id, selector, context) => Array.from(context.querySelectorAll(selector)).filter(element => {
        if (seen.has(element)) return false;
        seen.add(element);
        return true;
      });
      window.replacePane = keys => {
        const pane = document.querySelector('.checklist-resource-pane');
        Drupal.behaviors.checklistResourcePane.detach(pane, {}, 'unload');
        const replacement = pane.cloneNode(false);
        keys.forEach((key, index) => {
          const details = document.createElement('details');
          details.className = 'checklist-resource-content';
          details.id = key;
          details.name = 'resources';
          details.dataset.resourceKey = key;
          details.dataset.resourcePinned = key === 'a' ? 'true' : 'false';
          details.innerHTML = `<summary>${key}</summary><p>Content ${key}</p>`;
          details.open = index === 0;
          replacement.append(details);
        });
        pane.replaceWith(replacement);
        Drupal.behaviors.checklistResourcePane.attach(replacement);
      };
    });
    await page.addScriptTag({ path: path.resolve(__dirname, '../../js/interactive-checklist.js') });
    await page.evaluate(() => {
      Drupal.behaviors.checklistResourcePane.attach(document);
      replacePane(['a', 'b', 'c']);
    });
    await page.locator('#b summary').click();
    await page.evaluate(() => replacePane(['a', 'b', 'c']));
    assert(await page.locator('#b').evaluate(element => element.open));
    // A later behavior attachment must not restore an obsolete selection.
    await page.locator('#c summary').click();
    await page.evaluate(() => Drupal.behaviors.checklistResourcePane.attach(document));
    assert(await page.locator('#c').evaluate(element => element.open));
    await page.locator('button[data-checklist-workspace-view=resources]').click();
    await page.locator('#c summary').click();
    await page.evaluate(() => replacePane(['a', 'b', 'c']));
    assert(await page.locator('#c').evaluate(element => element.open));
    await page.locator('button[data-resource-key=a]').click();
    await page.evaluate(() => replacePane(['a', 'b', 'c']));
    assert.equal(await page.locator('.checklist-workspace').getAttribute('data-checklist-workspace-view'), 'resource:a');
    assert(await page.locator('#a').evaluate(element => element.open));
    await page.evaluate(() => replacePane(['b', 'c']));
    assert.equal(await page.locator('.checklist-workspace').getAttribute('data-checklist-workspace-view'), 'checklist');
    await page.evaluate(() => replacePane([]));
    assert(await page.locator('.checklist-workspace-mobile-navigation').evaluate(element => element.hidden));
    await page.evaluate(() => {
      document.body.insertAdjacentHTML('beforeend', '<table id="rows"><tbody><tr data-ciname="review" class="ci ci-inprogress"><td><form class="ci-row-form"><input type="checkbox"></form></td><td class="action-form-container"><textarea>Unsaved explanation</textarea></td></tr></tbody></table>');
      window.updateRow = state => Drupal.AjaxCommands.prototype.checklistItemState(null, {
        selector: '#rows', ciname: 'review',
        state: { visible: true, contexts_available: true, complete: false, failed: false, applicable: true, required: true, actionable: true, ...state }
      });
      updateRow({});
    });
    assert.equal(await page.locator('#rows textarea').inputValue(), 'Unsaved explanation');
    assert(await page.locator('#rows tr').evaluate(row => row.classList.contains('ci-inprogress')));
    await page.evaluate(() => updateRow({ actionable: false }));
    assert.equal(await page.locator('#rows textarea').count(), 0);
    assert(await page.locator('#rows input').isDisabled());
    assert(!(await page.locator('#rows tr').evaluate(row => row.classList.contains('ci-inprogress'))));
    // A freshly rebuilt reversible completion control must not be disabled.
    await page.locator('#rows input').evaluate(input => { input.disabled = false; });
    await page.evaluate(() => updateRow({ complete: true, actionable: false }));
    assert(await page.locator('#rows input').isEnabled());
    await page.evaluate(() => updateRow({ visible: false }));
    assert.equal(await page.locator('#rows tr').count(), 0);
    await page.evaluate(() => {
      document.querySelector('.checklist-workspace').insertAdjacentHTML('beforeend', '<table id="dynamic"><tbody><tr data-ciname="existing" class="ci ci-actionable ci-inprogress"><td>Old controls</td><td>Existing</td><td class="action-form-container"><textarea>Keep these edits</textarea></td></tr><tr data-ciname="removed"><td>Removed</td></tr></tbody></table><button data-checklist-complete>Complete</button>');
      window.liveAction = document.querySelector('#dynamic textarea');
      window.liveAction.focus();
      // Core AJAX detach removes every disconnected instance, not only those
      // inside its context. Retained forms must remain connected throughout.
      window.retainedAjax = true;
      Drupal.detachBehaviors = () => {
        if (!window.liveAction.isConnected) window.retainedAjax = false;
      };
      window.reconcile = (names, completable) => {
        const data = '<table><tbody>' + names.map(name => `<tr data-ciname="${name}" class="ci ci-actionable"><td>New controls</td><td>${name}</td><td class="action-form-container"></td></tr>`).join('') + '</tbody></table>';
        Drupal.AjaxCommands.prototype.checklistReconcileRows(null, { selector: '#dynamic', data, completable });
      };
      reconcile(['generated', 'existing'], false);
    });
    assert.deepEqual(await page.locator('#dynamic tr').evaluateAll(rows => rows.map(row => row.dataset.ciname)), ['generated', 'existing']);
    assert(await page.evaluate(() => document.querySelector('#dynamic textarea') === window.liveAction));
    assert.equal(await page.locator('#dynamic textarea').inputValue(), 'Keep these edits');
    assert(await page.evaluate(() => window.retainedAjax), 'Keep retained form AJAX instances alive');
    await page.evaluate(() => { Drupal.detachBehaviors = () => {}; });
    assert(await page.evaluate(() => document.activeElement === window.liveAction));
    assert(await page.locator('[data-checklist-complete]').isDisabled());
    await page.evaluate(() => reconcile(['existing', 'generated'], true));
    assert.equal(await page.locator('#dynamic tr').count(), 2);
    assert.deepEqual(await page.locator('#dynamic tr').evaluateAll(rows => rows.map(row => row.dataset.ciname)), ['existing', 'generated']);
    assert(await page.locator('[data-checklist-complete]').isEnabled());
    await page.evaluate(() => reconcile([], true));
    assert.equal(await page.locator('#dynamic tr').count(), 0);
    // An automatic row gains a form when it requests input, preserves edits
    // across refreshes, then drops the form when the request is satisfied.
    await page.evaluate(() => {
      window.inputRow = required => Drupal.AjaxCommands.prototype.checklistReconcileRows(null, {
        selector: '#dynamic', completable: false,
        data: `<table><tbody><tr data-ciname="automatic" data-input-required="${required}" class="ci ci-actionable"><td>Worker</td><td class="action-form-container">${required ? '<form><input name="reference"></form>' : ''}</td></tr></tbody></table>`
      });
      inputRow(false);
      inputRow(true);
    });
    assert.equal(await page.locator('#dynamic input[name="reference"]').count(), 1);
    await page.locator('#dynamic input').fill('Unsaved reference');
    await page.evaluate(() => inputRow(true));
    assert.equal(await page.locator('#dynamic input').inputValue(), 'Unsaved reference');
    await page.evaluate(() => inputRow(false));
    assert.equal(await page.locator('#dynamic input').count(), 0);
    // History opens alongside existing resources, retaining their live inputs.
    await page.evaluate(() => {
      replacePane(['a', 'b']);
      document.querySelector('#a').insertAdjacentHTML('beforeend', '<input value="Keep resource edits">');
      window.resourceInput = document.querySelector('#a input');
      window.openHistory = text => Drupal.AjaxCommands.prototype.checklistOpenResource(null, {
        selector: '.checklist-workspace',
        data: `<details id="history" name="resources" class="checklist-resource-content" data-checklist-history="true" data-resource-key="history:worker" data-resource-label="History: Worker"><summary>History: Worker</summary><p>${text}</p></details>`
      });
      openHistory('First page');
    });
    assert(await page.locator('#history').evaluate(panel => panel.open));
    assert.equal(await page.locator('.checklist-workspace').getAttribute('data-checklist-workspace-view'), 'resources');
    assert(await page.evaluate(() => document.querySelector('#a input') === window.resourceInput));
    await page.evaluate(() => openHistory('Second page'));
    assert.equal(await page.locator('[data-checklist-history]').count(), 1);
    assert.equal(await page.locator('#history p').innerText(), 'Second page');
    assert.equal(await page.locator('#a input').inputValue(), 'Keep resource edits');
    await page.evaluate(() => replacePane(['a']));
    assert.equal(await page.locator('#history p').innerText(), 'Second page');
    assert(await page.locator('#history').evaluate(panel => panel.open));
    // Row polling preserves a retry choice only for the same failed attempt.
    await page.evaluate(() => {
      window.retryRow = attempt => Drupal.AjaxCommands.prototype.checklistReconcileRows(null, {
        selector: '#dynamic', completable: false,
        data: `<table><tbody><tr data-ciname="worker"><td><div><a href="#">Retry</a><div id="retry-${attempt}" class="checklist-retry-slot"></div></div></td></tr></tbody></table>`
      });
      retryRow('first');
      document.querySelector('#retry-first').innerHTML = '<form><input value="fresh"><button type="button" class="checklist-retry-cancel">Cancel</button></form>';
      window.retryInput = document.querySelector('#retry-first input');
      retryRow('first');
    });
    assert(await page.evaluate(() => document.querySelector('#retry-first input') === window.retryInput));
    await page.locator('.checklist-retry-cancel').click();
    assert.equal(await page.locator('#retry-first form').count(), 0);
    await page.evaluate(() => {
      document.querySelector('#retry-first').innerHTML = '<form><input value="fresh"></form>';
      retryRow('successor');
    });
    assert.equal(await page.locator('#dynamic input').count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS: resources, row readiness, additions/removals/reordering, retained form identity/focus/edits completion controls, history tabs and inline retry cancellation/preservation.');
  }
  finally {
    await browser.close();
  }
})();
