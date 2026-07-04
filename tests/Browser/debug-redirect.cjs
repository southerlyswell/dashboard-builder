const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  page.on('response', r => {
    if (r.status() === 302 || r.url().includes('/login')) {
      console.log(r.status(), r.url().replace('http://dashboard-builder', ''));
    }
  });

  // Login
  await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);
  console.log('1. After login URL:', page.url());

  // Navigate to builder
  await page.goto('http://dashboard-builder/dashboard-builder?client=1', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);
  console.log('2. Builder URL:', page.url());
  console.log('3. Page title:', await page.title());

  const divs = await page.evaluate(() => {
    const app = document.querySelector('.app');
    return app ? 'APP EXISTS' : 'NO APP';
  });
  console.log('4. Alpine app:', divs);

  const ta = await page.evaluate(() => document.querySelectorAll('textarea').length);
  console.log('5. Textareas:', ta);

  await browser.close();
})();
