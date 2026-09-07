/**
 * duplicate-flow-check.cjs — quick E2E check of the Duplicate button.
 * Targets the running dev server (php artisan serve on 127.0.0.1:8000).
 *
 * Flow: login → open a client detail page → find a dashboard card →
 *       click Duplicate → verify a "(Copy)" card appears after reload.
 */
const { chromium } = require('playwright');

const BASE = 'http://127.0.0.1:8000';

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
    await page.waitForURL('**/projects', { timeout: 30000 }).catch(() => {});
    await page.waitForTimeout(2000);
    check('Login OK (projects page)', page.url().includes('/projects'));

    // 2. Find any client that has dashboard cards
    await page.waitForSelector('.client-card', { timeout: 20000 }).catch(() => {});
    const clientCards = await page.locator('.client-card').count();
    check('At least one client card exists', clientCards > 0);

    // Grab the first client's detail URL (the "View" link)
    const viewHref = await page.evaluate(() => {
      const card = document.querySelector('.client-card');
      if (!card) return null;
      const a = card.querySelector('a[href*="/dashboard-builder/clients/"]');
      return a ? a.getAttribute('href') : null;
    });
    check('Found client detail link', !!viewHref);

    if (viewHref) {
      await page.goto(BASE + viewHref, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(2000);

      const dashCount = await page.locator('.db-card').count();
      check('Client has at least one dashboard card', dashCount > 0);

      if (dashCount > 0) {
        // 3. Count dashboards before
        const beforeNames = await page.evaluate(() =>
          Array.from(document.querySelectorAll('.db-card-title')).map(el => el.textContent.trim())
        );
        check('Dashboard cards listed before', beforeNames.length > 0);

        // 4. Click the Duplicate button on the first card
        const clicked = await page.evaluate(() => {
          const btn = document.querySelector('.db-card-actions a[onclick*="duplicateDashboard"]');
          if (!btn) return false;
          btn.click();
          return true;
        });
        check('Duplicate button found & clicked', clicked === true);

        // Handle the alert dialog
        page.once('dialog', async (d) => { console.log('  (dialog):', d.message()); await d.accept(); });

        await page.waitForTimeout(3000);

        // 5. After reload, verify a "(Copy)" card exists
        const afterNames = await page.evaluate(() =>
          Array.from(document.querySelectorAll('.db-card-title')).map(el => el.textContent.trim())
        );
        const copyFound = afterNames.some(n => /copy/i.test(n));
        check('A "(Copy)" dashboard appears after duplicate', copyFound === true);
        check('Total cards increased', afterNames.length > beforeNames.length);

        await page.screenshot({ path: 'C:/laragon/www/dashboard-builder/tests/Browser/screenshots/duplicate-flow.png', fullPage: true });
      }
    }
  } catch (e) {
    console.log('[ERROR]', e.message);
    fail++;
  } finally {
    console.log('\n=== ' + pass + '/' + (pass + fail) + ' ===');
    await browser.close();
  }
})();
