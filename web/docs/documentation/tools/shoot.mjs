// Takes the clean screenshots for the documentation: phone screens from the app (web preview, a 390x844 phone at 2x) and the church and super admin
// portals (1440x900). Only the local development server is used, with the development accounts.
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const OUT = 'C:/xampp/htdocs/faithfellowapp-sedu/web/docs/documentation/screens';
fs.mkdirSync(OUT, { recursive: true });
const token = fs.readFileSync('token.txt', 'utf8').trim();
const APP = 'http://localhost:8084';
const WEB = 'http://localhost:8000';
const env = Object.fromEntries(fs.readFileSync('C:/xampp/htdocs/faithfellowapp-sedu/web/.env', 'utf8').split(/\r?\n/).filter((l) => /^DEMO_[A-Z_]+=/.test(l)).map((l) => [l.split('=')[0], l.slice(l.indexOf('=') + 1).trim().replace(/^"|"$/g, '')]));

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new', args: ['--no-sandbox', '--hide-scrollbars'] });

async function phone(name, route, { authed = true, wait = 9000, scroll = 0, act = null, quality = 86 } = {}) {
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  await page.evaluateOnNewDocument((t, a) => {
    localStorage.setItem('onboarded_v1', '1');
    if (a) localStorage.setItem('auth_token', t);
  }, token, authed);
  try {
    await page.goto(APP + route, { waitUntil: 'networkidle2', timeout: 90000 });
  } catch (e) {
    console.log('slow', name, e.message);
  }
  await sleep(wait);
  if (scroll) {
    await page.evaluate((y) => {
      const el = [...document.querySelectorAll('div')].filter((d) => d.scrollHeight > d.clientHeight + 50 && getComputedStyle(d).overflowY !== 'visible').sort((a, b) => b.scrollHeight - a.scrollHeight)[0];
      if (el) el.scrollTop = y;
    }, scroll);
    await sleep(900);
  }
  if (act) {
    await act(page);
    await sleep(1200);
  }
  await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality });
  console.log('shot', name);
  await page.close();
}

const click = (sel) => async (page) => {
  await page.evaluate((s) => document.querySelector(s)?.click(), sel);
};

// ── The app ─────────────────────────────────────────────────────────────────────────────────────────────────
await phone('app-welcome', '/welcome', { authed: false, wait: 6000 });
await phone('app-login', '/login', { authed: false, wait: 3000 });
await phone('app-home-greeting', '/', { wait: 3500 });
await phone('app-home-explore', '/', { wait: 13000 });
await phone('app-home-inspiration', '/', { wait: 13000, scroll: 560 });
await phone('app-home-verse', '/', { wait: 13000, scroll: 1300 });
await phone('app-home-lists', '/', { wait: 13000, scroll: 2400 });
await phone('app-bible', '/bible', { wait: 9000 });
await phone('app-bible-versions', '/bible', { wait: 9000, act: click('[aria-label^="Bible version"]') });
await phone('app-bible-books', '/bible', { wait: 9000, scroll: 640 });
await phone('app-reader', '/bible/JHN/3', { wait: 11000 });
await phone('app-community', '/community', { wait: 9000 });
await phone('app-people', '/people', { wait: 9000 });
await phone('app-games', '/games', { wait: 9000 });
await phone('app-game-lobby', '/games', { wait: 9000, act: async (page) => {
  await page.evaluate(() => document.querySelector('[aria-label="Start a game of Creation & Genesis"]')?.click());
  await sleep(5000);
} });
await phone('app-game-invite', '/games', { wait: 9000, act: async (page) => {
  await page.evaluate(() => document.querySelector('[aria-label="Start a game of Moses & the Exodus"]')?.click());
  await sleep(5000);
  await page.evaluate(() => document.querySelector('[aria-label="Invite people you have played with"]')?.click());
  await sleep(2500);
} });
await phone('app-give', '/give', { wait: 10000 });
await phone('app-give-form', '/give/grace-lira', { wait: 10000 });
const campaigns = await (await fetch(`${WEB}/api/v1/campaigns`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })).json();
const campaignId = campaigns.data?.[0]?.id;
if (campaignId) {
  await phone('app-campaign', `/campaign/${campaignId}`, { wait: 10000, scroll: 250 });
  await phone('app-donate', `/campaign/${campaignId}`, { wait: 10000, act: async (page) => {
    await page.evaluate(() => [...document.querySelectorAll('[role="button"],button,div')].find((e) => e.getAttribute?.('aria-label') === 'Donate' || e.innerText?.trim() === 'Donate')?.click());
    await sleep(2000);
  } });
}
await phone('app-profile-top', '/profile', { wait: 11000 });
await phone('app-profile-progress', '/profile', { wait: 11000, scroll: 760 });
await phone('app-profile-numbers', '/profile', { wait: 11000, scroll: 1250 });
await phone('app-stats', '/stats', { wait: 10000 });
await phone('app-podcasts', '/podcasts', { wait: 9000 });
await phone('app-hymns', '/hymns', { wait: 9000 });
await phone('app-events', '/events', { wait: 9000 });
await phone('app-prayer', '/prayer', { wait: 9000 });
await phone('app-notification-settings', '/settings/notifications', { wait: 9000 });

// ── The church portal and the super admin ───────────────────────────────────────────────────────────────────
async function portal(email, password, shots) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1.5 });
  await page.goto(`${WEB}/login`, { waitUntil: 'networkidle2', timeout: 60000 });
  await page.type('input[name="email"]', email);
  await page.type('input[name="password"]', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }), page.keyboard.press('Enter')]);
  for (const [name, path, full] of shots) {
    await page.goto(WEB + path, { waitUntil: 'networkidle2', timeout: 60000 });
    await sleep(1500);
    await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 84, fullPage: !!full });
    console.log('shot', name);
  }
  await context.close();
}

await portal(env.DEMO_CHURCH_ADMIN_EMAIL, env.DEMO_CHURCH_ADMIN_PASSWORD, [
  ['web-church-dashboard', '/church/grace-lira/admin/dashboard'],
  ['web-church-records', '/church/grace-lira/admin/giving/records', true],
  ['web-church-reasons', '/church/grace-lira/admin/giving/reasons'],
  ['web-church-billing', '/church/grace-lira/admin/billing'],
  ['web-church-subscribers', '/church/grace-lira/admin/subscribers', true],
  ['web-church-analytics', '/church/grace-lira/admin/analytics'],
]);
await portal(env.DEMO_SUPER_ADMIN_EMAIL, env.DEMO_SUPER_ADMIN_PASSWORD, [
  ['web-super-dashboard', '/super-admin/dashboard'],
  ['web-super-subscribers', '/super-admin/subscribers'],
  ['web-super-inspiration', '/super-admin/inspiration'],
  ['web-super-email', '/super-admin/email'],
  ['web-super-push', '/super-admin/push'],
  ['web-super-plans', '/super-admin/plans'],
]);

await browser.close();
console.log('done');
