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
      window.Drupal = { behaviors: {}, t: text => text, AjaxCommands: function () {} };
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
    assert.deepEqual(errors, []);
    console.log('PASS: resource selection survives replacement; removed resources fall back safely.');
  }
  finally {
    await browser.close();
  }
})();
