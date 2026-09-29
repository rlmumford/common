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
      window.Drupal = { detachBehaviors: () => {}, behaviors: {}, t: text => text, AjaxCommands: function () {} };
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
    assert.deepEqual(errors, []);
    console.log('PASS: resource selection, row readiness, preserved active edits, blocked-form closure and hidden-row removal.');
  }
  finally {
    await browser.close();
  }
})();
