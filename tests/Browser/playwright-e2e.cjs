const { chromium } = require('@playwright/test');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', err => consoleErrors.push('PAGE_ERROR: ' + err.message));

  let passed = 0, total = 0;
  function check(name, ok, detail) { total++; console.log((ok ? '✅' : '❌') + ' ' + name + (detail ? ' (' + detail + ')' : '')); if (ok) passed++; }

  try {
    await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(2000);
    await page.fill('input[type="email"], input[name="email"]', 'admin@dashboard-builder.test');
    await page.fill('input[type="password"], input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    check('Login', page.url().includes('dashboard'), 'URL: ' + page.url());

    await page.goto('http://dashboard-builder/dashboard-builder', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(3000);
    check('AI status dot', (await page.locator('.ai-dot').count()) > 0);
    check('Sidebar', (await page.locator('.sidebar').count()) > 0);

    // Click Overview
    await page.locator('button.quick-btn:has-text("Overview")').first().click({ timeout: 5000 });
    let cardsRendered = false;
    for (let i = 0; i < 90; i++) {
      await page.waitForTimeout(1000);
      if ((await page.locator('.dashboard-card').count()) > 0) { cardsRendered = true; check('Dashboard render', true, (i+1)+'s'); break; }
    }
    if (!cardsRendered) check('Dashboard render', false, 'timeout');

    // Card click → modal → check move arrows
    if (cardsRendered) {
      await page.waitForTimeout(2000);
      const canvasDims = await page.evaluate(() => Array.from(document.querySelectorAll('.dashboard-card canvas')).map(c => ({w:c.width,h:c.height})));
      check('ECharts rendering', canvasDims.length > 0 && canvasDims.every(c => c.w > 0 && c.h > 0), canvasDims.length + ' canvases');

      await page.locator('.dashboard-card').first().click({ timeout: 5000 });
      await page.waitForTimeout(500);
      check('Modal opens', await page.locator('.modal-overlay').isVisible());

      // Check move arrows exist
      const moveBtns = await page.locator('.move-btn').count();
      check('Move arrows (4)', moveBtns === 4, 'found: ' + moveBtns);

      // Test move right
      await page.locator('.move-btn:has-text("→")').click({ timeout: 5000 });
      await page.waitForTimeout(500);
      const gridCol = await page.locator('.dashboard-card').first().evaluate(el => el.style.gridColumn);
      check('Move right', gridCol.includes('/ span'), 'gridColumn: ' + gridCol);

      // Re-open modal, test move down
      await page.locator('.dashboard-card').first().click({ timeout: 5000 });
      await page.waitForTimeout(300);
      await page.locator('.move-btn:has-text("↓")').click({ timeout: 5000 });
      await page.waitForTimeout(500);
      const gridRow = await page.locator('.dashboard-card').first().evaluate(el => el.style.gridRow);
      check('Move down', gridRow.includes('/ span'), 'gridRow: ' + gridRow);

      // Re-open modal, test width/height
      await page.locator('.dashboard-card').first().click({ timeout: 5000 });
      await page.waitForTimeout(300);
      check('Width buttons', (await page.locator('.width-btn').count()) === 4);
      check('Height buttons', (await page.locator('.height-btn').count()) === 8);
      await page.locator('.width-btn:has-text("3")').click({ timeout: 5000 });
      await page.waitForTimeout(300);
      check('Resize width', (await page.locator('.dashboard-card').first().evaluate(el => el.style.gridColumn)).includes('span 3'));

      // Close modal, delete
      await page.locator('.btn-close').click({ timeout: 5000 });
      await page.waitForTimeout(300);
      const beforeDel = await page.locator('.dashboard-card').count();
      // Scroll last card into view and force-click to bypass canvas overlap
      const lastCard = page.locator('.dashboard-card').last();
      await lastCard.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
      await lastCard.click({ timeout: 5000, force: true });
      await page.waitForTimeout(400);
      if (await page.locator('.btn-delete').isVisible()) {
        await page.locator('.btn-delete').click({ timeout: 5000 });
        await page.waitForTimeout(500);
        check('Delete card', (await page.locator('.dashboard-card').count()) === beforeDel - 1);
      } else {
        check('Delete card', false, 'modal not visible after force-click');
      }

      // JSON panel
      const allBtns = await page.locator('.actions button').all();
      for (const btn of allBtns) {
        const text = (await btn.textContent()).trim();
        if (text === 'JSON' || text === 'Close JSON') { await btn.click(); break; }
      }
      await page.waitForTimeout(300);
      check('JSON panel', await page.locator('.json-editor').isVisible());
    }

    check('Console errors', consoleErrors.length === 0, consoleErrors.length + ' errors' + (consoleErrors.length > 0 ? ': ' + consoleErrors.slice(0,2).join(' | ') : ''));
    await page.screenshot({ path: 'C:/laragon/www/dashboard-builders/Browser/screenshots/playwright-arrows.png', fullPage: true });

  } catch(e) { check('Test', false, e.message); }
  finally {
    await browser.close();
    console.log('\n' + passed + '/' + total + ' passed');
    process.exit(passed === total ? 0 : 1);
  }
})();
