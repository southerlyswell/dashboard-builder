const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  // Login
  await page.goto('http://dashboard-builder/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', 'admin@dashboard-builder.test');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(3000);

  const token = await page.evaluate(() => document.querySelector('meta[name="csrf-token"]')?.content || '');

  // Client 1: EduTech SA
  const r1 = await page.evaluate(async (t) => {
    const r = await fetch('/dashboard-builder/clients', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t },
      body: JSON.stringify({
        name: 'EduTech SA',
        contact_name: 'Thandi Mahlangu',
        contact_email: 'thandi@edutech.co.za',
        contact_phone: '011 555 8100',
        address_line1: '12 Education Lane',
        city: 'Pretoria',
        province: 'Gauteng',
        postal_code: '0002',
        industry: 'Education',
        db_host: '127.0.0.1',
        db_port: 3306,
        db_database: 'edutech_sa',
        db_username: 'root',
        db_password: '',
      })
    });
    return { status: r.status, json: await r.json() };
  }, token);
  console.log('EduTech SA:', r1.status, JSON.stringify(r1.json));

  // Client 2: MediCare Plus
  const r2 = await page.evaluate(async (t) => {
    const r = await fetch('/dashboard-builder/clients', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': t },
      body: JSON.stringify({
        name: 'MediCare Plus',
        contact_name: 'Dr. Rajesh Pillay',
        contact_email: 'rajesh@medicareplus.co.za',
        contact_phone: '031 555 9200',
        address_line1: '78 Marine Parade',
        city: 'Durban',
        province: 'KwaZulu-Natal',
        postal_code: '4001',
        industry: 'Healthcare',
        db_host: '127.0.0.1',
        db_port: 3306,
        db_database: 'medicare_plus',
        db_username: 'root',
        db_password: '',
      })
    });
    return { status: r.status, json: await r.json() };
  }, token);
  console.log('MediCare Plus:', r2.status, JSON.stringify(r2.json));

  await browser.close();
})();
