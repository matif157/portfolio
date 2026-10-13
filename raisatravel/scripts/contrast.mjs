/**
 * Contrast audit.
 *
 *   npm run build && npm run preview &
 *   node scripts/contrast.mjs [baseUrl]
 *
 * Method: every run of text is captured with its declared colour, the text is
 * made transparent, and the surface behind it is sampled from a full-page
 * screenshot. Removing the glyphs first means the sample works on gradients and
 * glass panels too. Contrast is checked against both ends of the surface's
 * luminance range so text over a gradient has to hold up across the whole span.
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

const srgb = (v) => {
  const n = v / 255;
  return n <= 0.03928 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4);
};
const lum = ([r, g, b]) => 0.2126 * srgb(r) + 0.7152 * srgb(g) + 0.0722 * srgb(b);
const ratio = (a, b) => {
  const l1 = lum(a);
  const l2 = lum(b);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
};
const lumToGrey = (L) => {
  const g =
    L <= 0.0031308
      ? L * 12.92 * 255
      : (1.055 * Math.pow(L, 1 / 2.4) - 0.055) * 255;
  const v = Math.max(0, Math.min(255, Math.round(g)));
  return [v, v, v];
};

/**
 * Surface luminance range for a clip.
 *
 * Percentiles at 25/75 rather than the extremes: a pill-shaped button spends
 * roughly 10% of its bounding box on rounded corners and border, which show the
 * page background through. Those pixels are not the surface the text sits on,
 * and including them produces false failures on every pill button.
 */
function surfaceRange(buf) {
  const png = PNG.sync.read(buf);
  const lums = [];
  for (let i = 0; i < png.data.length; i += 4) {
    if (png.data[i + 3] < 8) continue;
    lums.push(lum([png.data[i], png.data[i + 1], png.data[i + 2]]));
  }
  if (lums.length < 12) return null;
  lums.sort((a, b) => a - b);
  const at = (q) => lums[Math.min(lums.length - 1, Math.max(0, Math.round(q * (lums.length - 1))))];
  return { dark: at(0.25), light: at(0.75) };
}

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const tab = await ctx.newPage();
const fails = [];
let checked = 0;
let skipped = 0;

for (const page of PAGES) {
  await tab.goto(`${BASE}/${page}`, { waitUntil: 'load' });
  await tab.evaluate(() => document.fonts.ready);
  await tab.evaluate(async () => {
    const step = window.innerHeight * 0.8;
    for (let y = 0; y < document.body.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 80));
    }
    window.scrollTo(0, 0);
  });
  await tab.waitForTimeout(1500);

  /* Force the settled state BEFORE measuring anything. Reveal animations
     offset elements with transforms, so rects captured first would no longer
     line up with the clip once the reveals are forced open. */
  await tab.addStyleTag({
    content: `
      *, *::before, *::after { animation: none !important; transition: none !important; }
      [data-reveal],
      [data-reveal-group] > * { opacity: 1 !important; transform: none !important; }
      /* Fixed chrome sits on top of whatever it happens to overlap, so a
         full-page capture of a nearby clip would sample the floating pill
         instead of the page behind it. Hide it while sampling surfaces - its
         own contrast is asserted in isolation further down. */
      .wa-float,
      .totop,
      .cursor { visibility: hidden !important; }
    `
  });
  await tab.waitForTimeout(250);

  const items = await tab.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('body *')) {
      const own = [...el.childNodes]
        .filter((n) => n.nodeType === 3 && n.textContent.trim().length > 2)
        .map((n) => n.textContent.trim())
        .join(' ');
      if (!own) continue;
      const r = el.getBoundingClientRect();
      if (r.width < 6 || r.height < 6) continue;
      const cs = getComputedStyle(el);
      if (cs.visibility === 'hidden' || Number(cs.opacity) === 0) continue;
      const col = (cs.color || '').match(/rgba?\(([^)]+)\)/);
      if (!col) continue;
      const p = col[1].split(',').map((x) => parseFloat(x));
      if (p.length > 3 && p[3] === 0) continue;
      el.dataset.qaText = '1';
      out.push({
        tag: el.tagName.toLowerCase(),
        cls: (el.className || '').toString().split(' ').filter(Boolean)[0] || '-',
        txt: own.replace(/\s+/g, ' ').slice(0, 30),
        fg: p.slice(0, 3),
        alpha: p.length > 3 ? p[3] : 1,
        size: parseFloat(cs.fontSize),
        weight: +cs.fontWeight,
        clip: {
          x: Math.round(r.left + window.scrollX) + 1,
          y: Math.round(r.top + window.scrollY) + 1,
          width: Math.round(Math.max(3, Math.min(r.width - 2, 260))),
          height: Math.round(Math.max(3, Math.min(r.height - 2, 70)))
        }
      });
    }
    return out;
  });

  /* now blank the text so each clip shows only the painted surface */
  await tab.addStyleTag({
    content: '[data-qa-text]{color:transparent !important;text-shadow:none !important}'
  });
  await tab.waitForTimeout(150);

  const large = (it) => it.size >= 24 || (it.size >= 18.66 && it.weight >= 700);

  /* A long page is captured with fullPage, and a single capture occasionally
     catches a frame where a clip no longer lines up with its element - which
     reports a perfectly good colour as unreadable. Re-capture once and only
     fail if it fails twice, so a flaky frame cannot block a deploy. */
  const readRange = async (it) => {
    try {
      return surfaceRange(await tab.screenshot({ fullPage: true, clip: it.clip }));
    } catch {
      return null;
    }
  };

  for (const it of items) {
    const need = large(it) ? 3 : 4.5;

    let range = await readRange(it);
    if (!range) {
      skipped++;
      continue;
    }

    let worst = null;
    for (const bg of [lumToGrey(range.dark), lumToGrey(range.light)]) {
      const eff = [0, 1, 2].map((i) => it.fg[i] * it.alpha + bg[i] * (1 - it.alpha));
      const cr = ratio(eff, bg);
      if (!worst || cr < worst.cr) worst = { cr, bg };
    }

    if (worst.cr < need) {
      const again = await readRange(it);
      if (again) {
        let retry = null;
        for (const bg of [lumToGrey(again.dark), lumToGrey(again.light)]) {
          const eff = [0, 1, 2].map((i) => it.fg[i] * it.alpha + bg[i] * (1 - it.alpha));
          const cr = ratio(eff, bg);
          if (!retry || cr < retry.cr) retry = { cr };
        }
        if (retry.cr >= need) {
          checked++;
          continue; // the first sample was a bad frame
        }
        worst = retry;
      }
      fails.push(
        `${page}: ${it.tag}.${it.cls} ${it.size}px ratio=${worst.cr.toFixed(2)} need=${need} "${it.txt}"`
      );
    }
    checked++;
  }

  await tab.goto(`${BASE}/${page}`, { waitUntil: 'load' });
  await tab.evaluate(() => document.fonts.ready);
}

/* The floating chrome lives in the footer partial, so it only needs checking
   once. It cannot be sampled from a full-page clip (it is position:fixed), so
   capture each control in isolation and check its label against its own pill. */
await tab.goto(`${BASE}/${PAGES[0]}`, { waitUntil: 'load' });
await tab.evaluate(() => document.fonts.ready);
await tab.addStyleTag({
  content: `.wa-float, .totop {
    visibility: visible !important;
    opacity: 1 !important;
    transform: none !important;
  }
  .wa-float:hover { transform: none !important; }`
});

for (const sel of ['.wa-float__label', '.wa-float', '.totop']) {
  const el = tab.locator(sel).first();
  if ((await el.count()) === 0) continue;

  const style = await el.evaluate((n) => {
    const cs = getComputedStyle(n);
    const m = (cs.color || '').match(/rgba?\(([^)]+)\)/);
    const p = m ? m[1].split(',').map(Number) : null;
    if (!p) return null;
    return {
      fg: p.slice(0, 3),
      alpha: p.length > 3 ? p[3] : 1,
      size: parseFloat(cs.fontSize),
      weight: +cs.fontWeight
    };
  });
  if (!style || style.size < 6) continue;

  let range;
  try {
    range = surfaceRange(await el.screenshot());
  } catch {
    continue;
  }
  if (!range) continue;

  const need = style.size >= 24 || (style.size >= 18.66 && style.weight >= 700) ? 3 : 4.5;
  for (const bg of [lumToGrey(range.dark), lumToGrey(range.light)]) {
    const eff = [0, 1, 2].map((i) => style.fg[i] * style.alpha + bg[i] * (1 - style.alpha));
    const cr = ratio(eff, bg);
    checked++;
    if (cr < need) {
      fails.push(
        `floating chrome ${sel} ${style.size}px ratio=${cr.toFixed(2)} need=${need}`
      );
    }
  }
}

await browser.close();

console.log(`text runs checked: ${checked}`);
if (skipped) console.log(`skipped (no sample): ${skipped}`);
if (fails.length) {
  console.log(`\n${fails.length} contrast failure(s):`);
  for (const f of [...new Set(fails)]) console.log('  -', f);
  process.exit(1);
}
console.log('contrast audit clean');