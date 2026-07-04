const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded' });
  
  // Get CSRF token
  const csrf = await page.evaluate(() => {
    return document.querySelector('meta[name="csrf-token"]')?.content || 'NONE';
  });
  
  // Login
  await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);
  
  // Get token from logged-in session
  const token = await page.evaluate(() => {
    return document.querySelector('meta[name="csrf-token"]')?.content || 'NONE';
  });
  console.log('CSRF token:', token.substring(0, 20) + '...');
  
  // Create client
  const result = await page.evaluate(async (t) => {
    try {
      const r = await fetch('/dashboard-builder/clients', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t },
        body: JSON.stringify({ name: 'TestCo', industry: 'Tech' })
      });
      return { status: r.status, body: await r.text() };
    } catch(e) { return { error: e.message }; }
  }, token);
  
  console.log('Create client result:', JSON.stringify(result).substring(0, 300));
  
  // Now load builder
  if (result.status === 200) {
    const json = JSON.parse(result.body);
    await page.goto('http://dashboard-builder/dashboard-builder/?client=' + json.id, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    console.log('Builder URL:', page.url());
    const taCount = await page.locator('textarea').count();
    console.log('Textarea count:', taCount);
    console.log('Page title:', await page.title());
  }
  
  await browser.close();
})();
