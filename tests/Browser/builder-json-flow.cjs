/**
 * builder-json-flow.cjs — E2E test for the JSON button on the dashboard builder.
 *
 * Flow:
 *   1. Login
 *   2. Load builder with ?client=14&project=12 (the reported bug scenario)
 *   3. Wait for project layout to load from /projects/{id}/load
 *   4. Verify the JSON button is VISIBLE (it was hidden before the fix)
 *   5. Click JSON → verify the panel opens with non-empty JSON
 *   6. Modify the JSON → click Apply → verify the canvas updates
 *   7. Close the panel
 *
 * Usage:
 *   node tests/Browser/builder-json-flow.cjs [clientId] [projectId]
 *   e.g. node tests/Browser/builder-json-flow.cjs 14 12
 *
 * Requires a running server + login credentials from seed (admin@dashboard-builder.test / password).
 */
const { chromium } = require('playwright');

const BASE = 'http://dashboard-builder';
const CLIENT_ID = process.argv[2] || '14';
const PROJECT_ID = process.argv[3] || '12';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  let pass = 0, fail = 0;
  const check = (name, ok) => { console.log((ok ? 'PASS' : 'FAIL') + ': ' + name); ok ? pass++ : fail++; };

  try {
    // 1. Login
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    check('Login OK', page.url().includes('/projects'));

    // 2. Load the builder with client + project (the reported bug scenario)
    const builderUrl = BASE + '/dashboard-builder?client=' + CLIENT_ID + '&project=' + PROJECT_ID;
    await page.goto(builderUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    check('Builder URL has client+project', page.url().includes('client=' + CLIENT_ID) && page.url().includes('project=' + PROJECT_ID));

    // 3. Wait for the async project load + render
    await page.waitForTimeout(6000);

    // Project load API check
    const projectLoad = await page.evaluate(async (pid) => {
      try {
        const r = await fetch('/dashboard-builder/projects/' + pid + '/load');
        if (!r.ok) return { ok: false, status: r.status };
        const d = await r.json();
        return { ok: true, hasLayout: !!d.layout, cardCount: d.layout && d.layout.cards ? d.layout.cards.length : 0 };
      } catch (e) { return { ok: false, error: e.message }; }
    }, PROJECT_ID);
    check('Project load API returns layout', projectLoad.ok && projectLoad.hasLayout);

    // 4. JSON button must be VISIBLE (dashboard state populated after fix)
    const jsonBtnVisible = await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('.main-header .actions button'));
      const jsonBtn = btns.find(b => (b.textContent || '').trim() === 'JSON');
      if (!jsonBtn) return false;
      const style = getComputedStyle(jsonBtn);
      return style.display !== 'none' && jsonBtn.offsetParent !== null;
    });
    check('JSON button is visible', jsonBtnVisible === true);

    // 5. Click JSON → panel opens with non-empty content
    const clicked = await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('.main-header .actions button'));
      const jsonBtn = btns.find(b => (b.textContent || '').trim() === 'JSON');
      if (!jsonBtn) return false;
      jsonBtn.click();
      return true;
    });
    check('JSON button clickable', clicked === true);
    await page.waitForTimeout(1000);

    const panelOpen = await page.evaluate(() => {
      const overlay = document.querySelector('.json-overlay');
      const editor = document.querySelector('.json-editor');
      return !!overlay && overlay.style.display !== 'none' && !!editor && editor.value.length > 0;
    });
    check('JSON panel opens with content', panelOpen === true);

    const editorContent = await page.evaluate(() => {
      const editor = document.querySelector('.json-editor');
      return editor ? editor.value : '';
    });
    const parsed = JSON.parse(editorContent);
    check('JSON panel content parses as object', typeof parsed === 'object');
    check('JSON panel has cards array', Array.isArray(parsed.cards) && parsed.cards.length > 0);
    check('JSON panel has title', typeof parsed.title === 'string' && parsed.title.length > 0);

    // 6. Modify JSON → Apply → verify canvas updates
    const applied = await page.evaluate((content) => {
      const editor = document.querySelector('.json-editor');
      if (!editor) return false;
      editor.value = content;
      editor.dispatchEvent(new Event('input', { bubbles: true }));
      const applyBtn = Array.from(document.querySelectorAll('.json-actions button')).find(b => (b.textContent || '').trim() === 'Apply');
      if (!applyBtn) return false;
      applyBtn.click();
      return true;
    }, JSON.stringify({ ...parsed, title: 'E2E Updated Title', cards: parsed.cards }));
    check('Apply clicked', applied === true);
    await page.waitForTimeout(2000);

    const titleUpdated = await page.evaluate(() => {
      const h2 = document.querySelector('.main-header h2');
      return h2 && h2.textContent.trim() === 'E2E Updated Title';
    });
    check('Dashboard title updated after Apply', titleUpdated === true);

    // Canvas still renders cards
    const cardCount = await page.evaluate(() => document.querySelectorAll('.dashboard-card').length);
    check('Dashboard cards still render', cardCount > 0);

    // 7. Close the panel
    const closed = await page.evaluate(() => {
      const closeBtn = Array.from(document.querySelectorAll('.json-actions button')).find(b => (b.textContent || '').trim() === 'Close');
      if (!closeBtn) return false;
      closeBtn.click();
      return true;
    });
    check('Close button clickable', closed === true);
    await page.waitForTimeout(800);
    const panelHidden = await page.evaluate(() => {
      const overlay = document.querySelector('.json-overlay');
      return !!overlay && overlay.style.display === 'none';
    });
    check('JSON panel closes', panelHidden === true);

    await page.screenshot({ path: 'C:/laragon/www/dashboard-builder/tests/Browser/screenshots/builder-json-flow.png', fullPage: true });
  } catch (e) {
    console.log('[ERROR]', e.message);
    fail++;
  } finally {
    console.log('\n=== ' + pass + '/' + (pass + fail) + ' ===');
    await browser.close();
  }
})();
