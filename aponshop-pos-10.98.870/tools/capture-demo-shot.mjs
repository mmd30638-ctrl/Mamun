import { chromium } from 'playwright';

const url = process.argv[2] || 'http://127.0.0.1:8765/pos-draft-memo-layout-demo.html';
const out = process.argv[3] || '/opt/cursor/artifacts/pos-draft-memo-fullwidth-924.png';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
await page.goto(url, { waitUntil: 'networkidle' });
const cart = page.locator('.ame-pos-term-cart');
const box = await cart.boundingBox();
const main = page.locator('#main-content');
const mainBox = await main.boundingBox();
const ratio = box && mainBox ? box.width / mainBox.width : 0;
await page.screenshot({ path: out, fullPage: false });
console.log(JSON.stringify({ out, cartWidth: box?.width, mainWidth: mainBox?.width, widthRatio: ratio }));
await browser.close();