const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/projects', { timeout: 10000 });
  await page.waitForTimeout(1000);

  const token = await page.evaluate(() => document.querySelector('meta[name="csrf-token"]')?.content || '');
  console.log('Token:', token);

  const fullResp = await page.evaluate(async (t) => {
    try {
      const r = await fetch('/dashboard-builder/clients', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t },
        body: JSON.stringify({ name: 'TestCo' })
      });
      return { status: r.status, type: r.headers.get('content-type'), text: await r.text() };
    } catch(e) { return { error: e.message }; }
  }, token);

  console.log('Full response:', JSON.stringify(fullResp).substring(0, 2000));
  await browser.close();
})();
