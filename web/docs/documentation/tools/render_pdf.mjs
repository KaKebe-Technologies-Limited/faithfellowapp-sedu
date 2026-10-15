// Prints the documentation page to a PDF (A4) with Chrome.
import puppeteer from 'puppeteer-core';
import { pathToFileURL } from 'node:url';

const DIR = 'C:/xampp/htdocs/faithfellowapp-sedu/web/docs/documentation';
const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.goto(pathToFileURL(`${DIR}/FaithFellows-Documentation.html`).href, { waitUntil: 'networkidle0', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.pdf({
  path: `${DIR}/FaithFellows-Documentation.pdf`,
  format: 'A4',
  printBackground: true,
  preferCSSPageSize: true,
});
await browser.close();
console.log('pdf ok');
