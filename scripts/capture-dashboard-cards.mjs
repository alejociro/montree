import { mkdir } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = join(root, 'resources/images/landing/raw');
await mkdir(outDir, { recursive: true });

const baseURL = 'http://demo.montree.test';

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({ viewport: { width: 1440, height: 960 }, deviceScaleFactor: 2 });
const page = await context.newPage();

await page.goto(`${baseURL}/login`);
await page.locator('#email').fill('admin@demo.montree.test');
await page.locator('#password').fill('password');
await Promise.all([
    page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 15000 }),
    page.getByRole('button', { name: /iniciar sesión|entrar|login/i }).click(),
]);
await page.waitForLoadState('networkidle');
console.log('post-login url:', page.url());

async function shoot(url, filename, opts = {}) {
    await page.goto(`${baseURL}${url}`, { waitUntil: 'networkidle' });
    if (opts.waitFor) {
        await page.waitForSelector(opts.waitFor, { timeout: 10000 }).catch(() => {});
    }
    if (opts.click) {
        await page.click(opts.click).catch(() => {});
        await page.waitForTimeout(600);
    }
    await page.waitForTimeout(400);
    const target = opts.locator ? page.locator(opts.locator).first() : page;
    const path = join(outDir, filename);
    await target.screenshot({ path, animations: 'disabled' });
    console.log('saved', filename);
}

await page.setViewportSize({ width: 1440, height: 1500 });
await shoot('/admin/tours/7', 'sierra-nevada-tour.png');
await shoot('/admin/tours/6', 'pago-confirmado.png', { click: 'text=Pasajeros' });
await shoot('/admin/departures', 'cupos-hoy.png');

await browser.close();
