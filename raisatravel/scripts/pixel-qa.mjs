/**
 * Pixel QA.
 *
 *   npm run build && npm run preview -- --port 4173 &
 *   node scripts/pixel-qa.mjs [baseUrl]
 *
 * Structural audits read the DOM, so they happily pass while a headline is
 * translated out of view or collapsed to nothing. This screenshots each h1 and
 * reports how much of it is actually painted glyph, which is what caught the
 * per-word split that shipped a 707px tall, effectively blank hero.
 *
 * A real heading covers roughly 15-30% of its own bounding box. Near zero
 * means the text is not painting; a box far taller than its line count means
 * every word has been forced onto its own line.
 */

import { chromium } from 'playwright';
import { PNG } from 'pngjs';

const BASE = process.argv[2] || 'http://localhost:4173';
const PAGES = [
  'index.html',
  'about.html',
  'workforce-solutions.html',
  'vetting-process.html',
  'clients-industries.html',
  'contact.html',
  '404.html'
];

const MIN_INK = 8; // percent of the box that must be glyph pixels
const MAX_HEIGHT = 320; // taller than this and words are stacking vertically

const browser = await chromium.launch();
const tab = await browser.newPage({ viewport: { width: 1280, height: 900 } });

const fails = [];

for (const page of PAGES) {
  await tab.goto(`${BASE}/${page}`, { waitUntil: 'load' });
  await tab.evaluate(() => document.fonts.ready);
  /* let the reveal transition land before measuring ink */
  await tab.waitForTimeout(1200);

  const h1 = tab.locator('h1').first();
  if ((await h1.count()) === 0) {
    fails.push(`${page}: no <h1>`);
    continue;
  }

  const box = await h1.boundingBox();
  const label = (await h1.textContent()).trim().replace(/\s+/g, ' ').slice(0, 44);

  if (!box || box.width < 40 || box.height < 10) {
    fails.push(`${page}: h1 has no box (${box ? `${box.width}x${box.height}` : 'null'}) "${label}"`);
    continue;
  }

  const clip = {
    x: Math.max(0, box.x),
    y: Math.max(0, box.y),
    width: Math.min(box.width, 760),
    height: Math.min(box.height, 420)
  };
  const png = PNG.sync.read(await tab.screenshot({ clip }));

  let bright = 0;
  const levels = new Set();
  for (let i = 0; i < png.data.length; i += 4) {
    const l = 0.2126 * png.data[i] + 0.7152 * png.data[i + 1] + 0.0722 * png.data[i + 2];
    if (l > 150) bright++;
    levels.add(l > 8 ? Math.round(l / 16) : 0);
  }
  const ink = (bright / (png.width * png.height)) * 100;

  if (ink < MIN_INK) {
    fails.push(`${page}: h1 barely paints (${ink.toFixed(1)}% ink) - text is not visible "${label}"`);
  }
  if (box.height > MAX_HEIGHT) {
    fails.push(`${page}: h1 is ${Math.round(box.height)}px tall - words are likely stacking "${label}"`);
  }

  console.log(
    `${page.padEnd(24)} ${String(Math.round(box.width)).padStart(4)}x${String(Math.round(box.height)).padEnd(4)}` +
      `  ink ${ink.toFixed(1).padStart(5)}%  levels ${String(levels.size).padStart(3)}  "${label}"`
  );
}

await browser.close();

if (fails.length) {
  console.log(`\n${fails.length} pixel failure(s):`);
  for (const f of fails) console.log(`  - ${f}`);
  process.exit(1);
}
console.log('\npixel QA clean');
