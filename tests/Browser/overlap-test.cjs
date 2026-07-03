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

    await page.goto('http://dashboard-builder/dashboard-builder', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(3000);

    // Click Overview
    await page.locator('button.quick-btn:has-text("Overview")').first().click({ timeout: 10000 });
    let rendered = false;
    for (let i = 0; i < 90; i++) {
      await page.waitForTimeout(1000);
      if ((await page.locator('.dashboard-card').count()) > 0) { rendered = true; console.log('Rendered at ' + (i+1) + 's'); break; }
    }
    check('Dashboard renders', rendered);
    await page.waitForTimeout(3000);

    if (rendered) {
      // Get card data positions
      const cardData = await page.evaluate(() => {
        var el = document.querySelector('[x-data]');
        var data = el._x_dataStack[0];
        return data.dashboard.cards.map(c => ({
          id: c.id, type: c.type, w: c.w, h: c.h, col: c.col, row: c.row
        }));
      });
      console.log('Card positions:', JSON.stringify(cardData, null, 2));

      // Check for overlaps in the data model
      function overlaps(a, b) {
        return a.row < b.row + b.h && a.row + a.h > b.row && a.col < b.col + b.w && a.col + a.w > b.col;
      }
      let dataOverlaps = [];
      for (let i = 0; i < cardData.length; i++) {
        for (let j = i + 1; j < cardData.length; j++) {
          if (overlaps(cardData[i], cardData[j])) {
            dataOverlaps.push(cardData[i].id + ' <-> ' + cardData[j].id);
          }
        }
      }
      check('No data model overlaps', dataOverlaps.length === 0, dataOverlaps.length > 0 ? dataOverlaps.join(', ') : 'clean');

      // Check for visual overlaps using getBoundingClientRect
      const visualRects = await page.evaluate(() => {
        var cards = document.querySelectorAll('.dashboard-card');
        return Array.from(cards).map(el => {
          var r = el.getBoundingClientRect();
          return { id: el.getAttribute('data-card-id'), top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height };
        });
      });
      console.log('Visual rects:', JSON.stringify(visualRects.map(r => ({id:r.id, t:Math.round(r.top), b:Math.round(r.bottom), l:Math.round(r.left), r:Math.round(r.right), w:Math.round(r.width), h:Math.round(r.height)})), null, 2));

      // Check for visual overlaps (allowing 1px tolerance for borders)
      let visualOverlaps = [];
      for (let i = 0; i < visualRects.length; i++) {
        for (let j = i + 1; j < visualRects.length; j++) {
          var a = visualRects[i], b = visualRects[j];
          // Overlap if they intersect by more than 2px in both dimensions
          var dx = Math.min(a.right, b.right) - Math.max(a.left, b.left);
          var dy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
          if (dx > 2 && dy > 2) {
            visualOverlaps.push(a.id + ' <-> ' + b.id + ' (' + Math.round(dx) + 'x' + Math.round(dy) + 'px)');
          }
        }
      }
      check('No visual overlaps', visualOverlaps.length === 0, visualOverlaps.length > 0 ? visualOverlaps.join(', ') : 'clean');

      // Check card heights match grid allocation
      const heightChecks = await page.evaluate(() => {
        var cards = document.querySelectorAll('.dashboard-card');
        var results = [];
        Array.from(cards).forEach(el => {
          var r = el.getBoundingClientRect();
          var computed = window.getComputedStyle(el);
          var gridRow = computed.gridRow;
          var gridColumn = computed.gridColumn;
          results.push({
            id: el.getAttribute('data-card-id'),
            height: Math.round(r.height),
            gridRow: gridRow,
            gridColumn: gridColumn
          });
        });
        return results;
      });
      
      // Each card height should be approximately h * 50px + (h-1) * 8px (gaps)
      let heightIssues = [];
      cardData.forEach(c => {
        var expected = c.h * 50 + (c.h - 1) * 8; // 50px per row + 8px gap between rows
        var actual = heightChecks.find(h => h.id === c.id);
        if (actual) {
          var diff = Math.abs(actual.height - expected);
          if (diff > 5) { // 5px tolerance
            heightIssues.push(c.id + ': expected ~' + expected + 'px got ' + actual.height + 'px (h=' + c.h + ')');
          }
        }
      });
      check('Card heights match grid', heightIssues.length === 0, heightIssues.length > 0 ? heightIssues.join(', ') : 'all correct');

      // Check chart containers don't overflow their cards
      const chartOverflow = await page.evaluate(() => {
        var cards = document.querySelectorAll('.dashboard-card');
        var issues = [];
        Array.from(cards).forEach(card => {
          var chart = card.querySelector('.card-chart, canvas');
          if (chart) {
            var cardRect = card.getBoundingClientRect();
            var chartRect = chart.getBoundingClientRect();
            if (chartRect.bottom > cardRect.bottom + 2) {
              issues.push(card.getAttribute('data-card-id') + ': chart ' + Math.round(chartRect.bottom - cardRect.bottom) + 'px overflow');
            }
          }
        });
        return issues;
      });
      check('No chart overflow', chartOverflow.length === 0, chartOverflow.length > 0 ? chartOverflow.join(', ') : 'all contained');

      // Full card suite check
      const cardTypes = cardData.map(c => c.type);
      const hasAll = ['title','kpi','divider','header','line','subheader','donut','bar','table'].every(t => cardTypes.includes(t));
      check('Full card suite', hasAll, cardTypes.join(', '));

      // ECharts canvases
      const canvases = await page.locator('.dashboard-card canvas').count();
      check('ECharts canvases', canvases >= 2, canvases + ' canvases');

      // Modal still works
      const firstCard = page.locator('.dashboard-card').first();
      await firstCard.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
      await firstCard.click({ timeout: 5000, force: true });
      await page.waitForTimeout(500);
      check('Modal opens', await page.locator('.modal-overlay').isVisible());
      await page.locator('.btn-close').click({ timeout: 5000 });
      await page.waitForTimeout(300);
      check('Modal closes', !(await page.locator('.modal-overlay').isVisible()));

      // Console errors
      check('No console errors', consoleErrors.length === 0, consoleErrors.length + ' errors');

      await page.screenshot({ path: 'C:/laragon/www/dashboard-builders/Browser/screenshots/db-no-overlap.png', fullPage: true });
    }

  } catch(e) { check('Test', false, e.message); }
  finally {
    await browser.close();
    console.log('\n' + passed + '/' + total + ' passed');
    process.exit(passed === total ? 0 : 1);
  }
})();
