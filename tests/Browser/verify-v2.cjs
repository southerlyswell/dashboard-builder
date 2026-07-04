const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  
  await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);
  
  let pass = 0, fail = 0;
  function check(name, ok) { console.log(ok ? 'PASS' : 'FAIL', ':', name); if (ok) pass++; else fail++; }

  check('Projects page loads', page.url().includes('/projects'));
  
  const cardCount = await page.locator('.client-card').count();
  check('Client cards render (>0)', cardCount >= 1);

  // Check card structure
  const firstCardName = await page.locator('.client-name').first().textContent().catch(() => '');
  check('Client name visible', firstCardName.length > 0);

  const categories = await page.locator('.client-category').first().textContent().catch(() => '');
  check('Category badge visible', categories.length > 0);

  const actionButtons = await page.locator('.client-actions button, .client-actions a').count();
  check('Action buttons per card (8)', actionButtons >= 8); // 4 buttons × 2 cards

  // Check two-column detail layout
  const detailItems = await page.locator('.client-detail-item').count();
  check('Detail items (two-column)', detailItems >= 4);

  // Check no filter bar on builder page
  await page.goto('http://dashboard-builder/dashboard-builder?client=12', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);
  const filterBars = await page.locator('.filter-bar').count();
  check('No filter bar on builder', filterBars === 0);

  await page.screenshot({ path: 'C:/laragon/www/dashboard-builder/tests/Browser/screenshots/projects-v2.png', fullPage: true });
  console.log('\n=== ' + pass + '/' + (pass + fail) + ' ===');
  await browser.close();
})();
