const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  let pass = 0, fail = 0;
  function check(name, ok) { console.log(ok ? 'PASS' : 'FAIL', ': ' + name); if (ok) pass++; else fail++; }

  try {
    await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    check('Login OK', page.url().includes('/projects'));
    check('Body dark', await page.evaluate(() => getComputedStyle(document.body).backgroundColor) === 'rgb(15, 23, 42)');

    await page.goto('http://dashboard-builder/dashboard-builder?client=1', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(4000);
    check('Builder loads', page.url().includes('client='));
    check('Chat textarea', await page.evaluate(() => document.querySelectorAll('textarea').length) > 0);
    check('Filter bar', await page.evaluate(() => document.querySelectorAll('.filter-bar').length) > 0);
    check('Schema quick btn', await page.evaluate(() => Array.from(document.querySelectorAll('.quick-btn')).some(b => b.textContent.includes('Schema'))));
    check('Save btn-save', await page.evaluate(() => document.querySelector('button.btn-save') !== null));
    check('Embed API 404', await page.evaluate(async () => { try { const r = await fetch('/api/dashboard/x'); return r.status === 404; } catch(e) { return false; } }) === true);
    check('AI health API', await page.evaluate(async () => { const r = await fetch('/dashboard-builder/ai/health'); return (await r.json()).online; }) === true);

    const p2 = await browser.newPage();
    await p2.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await p2.waitForTimeout(1000);
    check('Register link', await p2.evaluate(() => document.querySelector('a[href*="register"]') !== null));
    await p2.close();

    console.log('\n=== ' + pass + '/' + (pass + fail) + ' ===');
  } catch(e) { console.log('[ERROR]', e.message); }
  finally { await browser.close(); }
})();
