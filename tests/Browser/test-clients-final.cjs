const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await context.newPage();
  page.on('console', m => console.log('['+m.type()+']', m.text()));
  page.on('response', r => {
    const url = r.url();
    if (url.includes('/clients') || url.includes('/projects')) {
      console.log('HTTP', r.request().method(), url.replace('http://dashboard-builder', ''), '->', r.status());
    }
  });

  try {
    // Login
    await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(2000);
    await page.fill('input[type="email"]', 'admin@dashboard-builder.test');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    console.log('Logged in:', page.url());

    // Go to projects page
    await page.goto('http://dashboard-builder/dashboard-builder/projects', { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(3000);
    console.log('Projects page:', page.url());

    // Check sidebar has Dashboard Builder link
    const hasNavLink = await page.locator('a.nav-item:has-text("Dashboard Builder")').count();
    console.log('Sidebar nav link:', hasNavLink > 0 ? 'YES' : 'NO');

    // Check clients loaded
    const clientCards = await page.locator('.client-card').count();
    console.log('Client cards:', clientCards);

    // Add a client
    console.log('\nAdding client...');
    await page.click('button:has-text("Add Client")');
    await page.waitForTimeout(500);
    
    const modalVisible = await page.locator('.modal-overlay').isVisible();
    console.log('Modal visible:', modalVisible);
    
    if (modalVisible) {
      await page.fill('input[placeholder="Acme Corp"]', 'Test Company Ltd');
      await page.fill('input[placeholder="John Smith"]', 'John Doe');
      await page.fill('input[placeholder="john@acme.co.za"]', 'john@testltd.co.za');
      await page.fill('input[placeholder="011 123 4567"]', '011 555 1234');
      await page.fill('input[placeholder="123 Main Street"]', '45 Test Avenue');
      await page.fill('input[placeholder="Johannesburg"]', 'Sandton');
      await page.fill('input[placeholder="Gauteng"]', 'Gauteng');
      await page.fill('input[placeholder="2000"]', '2196');
      await page.selectOption('select', 'Finance');
      
      await page.click('button:has-text("Save Client")');
      await page.waitForTimeout(1000);

      // Check result
      const afterCards = await page.locator('.client-card').count();
      console.log('Client cards after add:', afterCards);
      
      // Verify the new client appears
      const hasNewClient = await page.locator('.client-name').count();
      console.log('Client name elements:', hasNewClient);
    }

    await page.screenshot({ path: 'C:/laragon/www/dashboard-builder/tests/Browser/screenshots/clients-final.png', fullPage: true });

  } catch(e) { console.log('ERROR:', e.message); }
  finally { await browser.close(); }
})();
