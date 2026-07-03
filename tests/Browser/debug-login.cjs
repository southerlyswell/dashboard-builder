const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await context.newPage();

  // Login
  await page.goto('http://dashboard-builder/login', { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1000);
  console.log('On page:', page.url());

  await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);

  console.log('After login:', page.url());

  if (page.url().includes('login')) {
    // Check for error messages
    const body = await page.textContent('body');
    if (body.includes('These credentials')) {
      console.log('ERROR: Invalid credentials');
    } else if (body.includes('csrf') || body.includes('419')) {
      console.log('ERROR: CSRF issue');
    } else {
      console.log('Login page shown, checking form...');
      const html = await page.content();
      console.log('Page snippet:', html.substring(0, 500));
    }
  }

  await page.screenshot({ path: 'C:/laragon/www/dashboard-builder/tests/Browser/screenshots/after-login.png' });
  console.log('Cookies:', (await context.cookies()).map(c => c.name + '=' + c.value.substring(0,20)).join('; '));

  await page.goto('http://dashboard-builder/dashboard-builder/projects', { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(2000);
  console.log('Projects page:', page.url());
  await page.screenshot({ path: 'C:/laragon/www/dashboard-builder/tests/Browser/screenshots/projects-page.png' });

  await browser.close();
})();
