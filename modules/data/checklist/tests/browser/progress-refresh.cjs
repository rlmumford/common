// Polling lifecycle regression using production behavior and a mocked transport.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await page.setContent('<div data-checklist-refresh-url="/refresh"><div data-refresh-progress="true"></div><input></div>');
    await page.evaluate(() => {
      window.jobs = new Map();
      let id = 0;
      window.setTimeout = (fn, delay) => { jobs.set(++id, { fn, delay }); return id; };
      window.clearTimeout = key => jobs.delete(key);
      window.tick = () => { const [key, job] = jobs.entries().next().value; jobs.delete(key); job.fn(); };
      window.hidden = false;
      Object.defineProperty(document, 'hidden', { get: () => window.hidden });
      window.jQuery = { active: 0 };
      window.requests = 0;
      window.applied = 0;
      window.Drupal = { behaviors: {}, t: value => value, ajax: settings => {
        window.transport = {
          options: { complete() {} }, instanceIndex: 0,
          success() { applied++; },
          execute() { requests++; return { always(fn) { window.finish = fn; } }; }
        };
        return transport;
      } };
      Drupal.ajax.instances = [];
    });
    await page.addScriptTag({ path: path.resolve(__dirname, '../../js/checklist-progress-refresh.js') });
    await page.evaluate(() => { Drupal.behaviors.checklistProgressRefresh.attach(document); tick(); });
    assert.equal(await page.evaluate(() => requests), 1);
    await page.evaluate(() => Drupal.behaviors.checklistProgressRefresh.attach(document));
    assert.equal(await page.evaluate(() => jobs.size), 0, 'No overlapping requests');
    await page.locator('input').fill('Keep my edits');
    await page.evaluate(async () => { await transport.success([], 'success'); finish(); });
    assert.equal(await page.evaluate(() => applied), 0, 'Discard response preceding user interaction');
    await page.evaluate(() => { hidden = true; tick(); });
    assert.equal(await page.evaluate(() => requests), 1, 'Pause hidden pages');
    await page.evaluate(() => { hidden = false; jQuery.active = 1; tick(); });
    assert.equal(await page.evaluate(() => requests), 1, 'Pause during form AJAX');
    await page.evaluate(async () => { jQuery.active = 0; tick(); await transport.success([], 'success'); finish(); });
    assert.equal(await page.evaluate(() => applied), 1);
    await page.evaluate(() => { tick(); transport.error({ status: 503 }); finish(); });
    assert.equal(await page.evaluate(() => [...jobs.values()][0].delay), 10000);
    assert.equal(await page.locator('[role="status"]').isVisible(), true);
    await page.evaluate(() => { tick(); transport.error({ status: 403 }); finish(); });
    assert.equal(await page.evaluate(() => jobs.size), 0, 'Stop on denied access');
    await page.evaluate(() => {
      const workspace = document.querySelector('[data-checklist-refresh-url]');
      Drupal.behaviors.checklistProgressRefresh.detach(workspace, {}, 'unload');
      Drupal.behaviors.checklistProgressRefresh.attach(document);
      tick();
      document.querySelector('[data-refresh-progress]').dataset.refreshProgress = 'false';
      finish();
    });
    assert.equal(await page.evaluate(() => jobs.size), 0, 'Stop when nothing needs watching');
    await page.evaluate(() => {
      document.querySelector('[data-refresh-progress]').dataset.refreshProgress = 'true';
      Drupal.behaviors.checklistProgressRefresh.attach(document);
    });
    assert.equal(await page.evaluate(() => jobs.size), 1, 'Restart after an input submission');
    await page.evaluate(() => Drupal.behaviors.checklistProgressRefresh.detach(document, {}, 'unload'));
    assert.equal(await page.evaluate(() => jobs.size), 0);
    console.log('PASS: single request, interaction fencing, hidden/busy pause, backoff, access stop, terminal stop and restart.');
  }
  finally { await browser.close(); }
})();
