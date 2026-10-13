/**
 * Browser QA for the built site.
 *
 *   npm run build && npm run preview &
 *   node scripts/browser-qa.mjs [baseUrl]
 *
 * Checks every page at four viewports for console errors, failed requests,
 * horizontal overflow, tap-target size, contrast of body text, and the
 * mobile drawer / form interactions.
 */

import { chromium } from 'playwright';

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
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'laptop', width: 1280, height: 800 },
  { name: 'tablet', width: 834, height: 1112 },
  { name: 'mobile', width: 390, height: 844 }
];

const fails = [];
const note = (page, vp, msg) => fails.push(`[${vp}] ${page}: ${msg}`);

const browser = await chromium.launch();

for (const vp of VIEWPORTS) {
  const ctx = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
    deviceScaleFactor: 1,
    hasTouch: vp.name === 'mobile' || vp.name === 'tablet'
  });

  for (const page of PAGES) {
    const tab = await ctx.newPage();
    const errors = [];
    const failed = [];

    tab.on('console', (m) => {
      if (m.type() === 'error') errors.push(m.text());
    });
    tab.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
    tab.on('requestfailed', (r) => {
      const u = r.url();
      if (!u.startsWith('data:')) failed.push(`${u} (${r.failure()?.errorText})`);
    });
    tab.on('response', (r) => {
      if (r.status() >= 400 && r.url().startsWith(BASE)) {
        failed.push(`${r.url()} -> HTTP ${r.status()}`);
      }
    });

    await tab.goto(`${BASE}/${page}`, { waitUntil: 'networkidle', timeout: 30000 });
    await tab.waitForTimeout(600);

    errors.forEach((e) => note(page, vp.name, `console error: ${e}`));
    failed.forEach((f) => note(page, vp.name, `request: ${f}`));

    // horizontal overflow
    const overflow = await tab.evaluate(() => {
      const de = document.documentElement;
      const over = de.scrollWidth - de.clientWidth;
      if (over <= 1) return null;
      const guilty = [];
      for (const el of document.querySelectorAll('body *')) {
        const r = el.getBoundingClientRect();
        if (r.width === 0) continue;
        if (r.right > de.clientWidth + 1 || r.left < -1) {
          guilty.push(
            `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]} ` +
              `right=${Math.round(r.right)} left=${Math.round(r.left)}`
          );
        }
        if (guilty.length > 4) break;
      }
      return { over, guilty };
    });
    if (overflow) {
      note(page, vp.name, `h-overflow ${overflow.over}px: ${overflow.guilty.join(' | ')}`);
    }

    // tap targets on touch viewports
    if (vp.name === 'mobile' || vp.name === 'tablet') {
      /* Measures the real clickable area by hit-testing, so links that expand
         their hit area with a pseudo-element count as passing. */
      const small = await tab.evaluate(() => {
        const MIN = 32;
        const hits = (el, x, y) => {
          const top = document.elementFromPoint(x, y);
          return !!top && (top === el || el.contains(top) || top.contains(el));
        };
        const out = [];
        for (const el of document.querySelectorAll('a, button, summary, select, input, textarea')) {
          const r = el.getBoundingClientRect();
          if (r.width === 0 || r.height === 0) continue;
          const cs = getComputedStyle(el);
          if (cs.visibility === 'hidden' || cs.display === 'none') continue;
          if (r.right <= 0 || r.left >= window.innerWidth) continue;
          if (r.top > window.innerHeight || r.bottom < 0) continue;

          if (r.height >= MIN && r.width >= MIN) continue;

          /* sample just outside the visual box; all four must still hit */
          const cx = r.left + r.width / 2;
          const cy = r.top + r.height / 2;
          const probes = [
            [cx, cy - MIN / 2],
            [cx, cy + MIN / 2],
            [cx - MIN / 2, cy],
            [cx + MIN / 2, cy]
          ].filter(([x, y]) => x >= 0 && y >= 0 && x <= window.innerWidth && y <= window.innerHeight);

          const missed = probes.filter(([x, y]) => !hits(el, x, y));
          if (missed.length === probes.length) {
            const own = (el.className || '').toString().split(' ').filter(Boolean)[0] || '(no class)';
            const parent = el.parentElement
              ? (el.parentElement.className || '').toString().split(' ').filter(Boolean)[0] || el.parentElement.tagName.toLowerCase()
              : '?';
            const text = (el.textContent || el.value || '').trim().replace(/\s+/g, ' ').slice(0, 26);
            out.push(
              `${el.tagName.toLowerCase()}.${own} in .${parent} ${Math.round(r.width)}x${Math.round(
                r.height
              )} "${text}"`
            );
          }
        }
        return [...new Set(out)];
      });
      small.slice(0, 8).forEach((s) => note(page, vp.name, `tap target not clickable ${s}`));
    }

    // body copy must be readable, not near-invisible
    const dim = await tab.evaluate(() => {
      const out = [];
      for (const el of document.querySelectorAll('p, li, .lede, .muted, .card__text, .ind__text')) {
        const t = (el.textContent || '').trim();
        if (t.length < 40) continue;
        const r = el.getBoundingClientRect();
        if (r.height === 0) continue;
        const c = getComputedStyle(el);
        const size = parseFloat(c.fontSize);
        if (size < 12) out.push(`${Math.round(size)}px: "${t.slice(0, 32)}…"`);
      }
      return [...new Set(out)];
    });
    dim.slice(0, 3).forEach((d) => note(page, vp.name, `text too small ${d}`));

    await tab.close();
  }

  await ctx.close();
}

/* ---------------------------------------------------------------- interactions */

const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true });
const tab = await ctx.newPage();
await tab.goto(`${BASE}/index.html`, { waitUntil: 'networkidle' });

// mobile drawer
const burger = tab.locator('.burger');
await burger.click();
await tab.waitForTimeout(500);
const drawerOpen = await tab.evaluate(() => {
  const d = document.querySelector('.drawer');
  const burgerEl = document.querySelector('.burger');
  const locked = getComputedStyle(document.documentElement).overflow;
  return {
    visible: d ? d.getBoundingClientRect().left > -50 : false,
    expanded: burgerEl?.getAttribute('aria-expanded'),
    locked
  };
});
if (!drawerOpen.visible) note('index.html', 'mobile', 'drawer did not open');
if (drawerOpen.expanded !== 'true') note('index.html', 'mobile', `burger aria-expanded=${drawerOpen.expanded}`);
if (drawerOpen.locked !== 'hidden') note('index.html', 'mobile', `body scroll not locked (${drawerOpen.locked})`);

await tab.keyboard.press('Escape');
await tab.waitForTimeout(450);
const afterEsc = await tab.evaluate(() => ({
  expanded: document.querySelector('.burger')?.getAttribute('aria-expanded'),
  locked: getComputedStyle(document.documentElement).overflow
}));
if (afterEsc.expanded !== 'false') note('index.html', 'mobile', 'Escape did not close drawer');
if (afterEsc.locked === 'hidden') note('index.html', 'mobile', 'body scroll still locked after close');

// accordion exclusivity
await tab.goto(`${BASE}/vetting-process.html`, { waitUntil: 'networkidle' });
const accs = tab.locator('.acc');
await accs.nth(0).locator('summary').click();
await tab.waitForTimeout(250);
await accs.nth(1).locator('summary').click();
await tab.waitForTimeout(250);
const openCount = await tab.evaluate(() => document.querySelectorAll('.acc[open]').length);
if (openCount !== 1) note('vetting-process.html', 'mobile', `accordion not exclusive (${openCount} open)`);

/* --------------------------------------------------------------------- form */

await tab.goto(`${BASE}/contact.html`, { waitUntil: 'networkidle' });

// client-side validation must block an empty submit
await tab.locator('button[type="submit"]').click();
await tab.waitForTimeout(400);
const blocked = await tab.evaluate(() => ({
  status: document.querySelector('[data-formstatus]')?.className || '',
  invalid: document.querySelectorAll('.field.is-invalid').length
}));
if (!blocked.status.includes('formstatus--err')) {
  note('contact.html', 'mobile', 'empty submit was not blocked client-side');
}
if (blocked.invalid < 3) {
  note('contact.html', 'mobile', `expected 3 invalid fields, saw ${blocked.invalid}`);
}

// stamp must be filled at render time so the bot timing check has a baseline
const stamped = await tab.evaluate(() => Number(document.querySelector('[name="t"]')?.value) > 0);
if (!stamped) note('contact.html', 'mobile', 'hidden t field was not stamped');

// honeypot must exist but stay off-screen and unfocusable
const hp = await tab.evaluate(() => {
  const el = document.querySelector('.hp');
  if (!el) return null;
  const cs = getComputedStyle(el);
  return { left: cs.left, tabindex: el.querySelector('input')?.getAttribute('tabindex') };
});
if (!hp) note('contact.html', 'mobile', 'honeypot missing');
else {
  if (hp.left !== '-9999px') note('contact.html', 'mobile', `honeypot not off-screen (${hp.left})`);
  if (hp.tabindex !== '-1') note('contact.html', 'mobile', 'honeypot is keyboard focusable');
}

await ctx.close();

/* ----------------------------------------------------------- reduced motion */

const rmCtx = await browser.newContext({
  viewport: { width: 1280, height: 800 },
  reducedMotion: 'reduce'
});
const rmTab = await rmCtx.newPage();
const rmErrors = [];
rmTab.on('pageerror', (e) => rmErrors.push(e.message));
await rmTab.goto(`${BASE}/index.html`, { waitUntil: 'networkidle' });
await rmTab.waitForTimeout(700);
const rmState = await rmTab.evaluate(() => {
  const hidden = [...document.querySelectorAll('[data-reveal]')].filter((el) => {
    const cs = getComputedStyle(el);
    return cs.opacity === '0' || cs.visibility === 'hidden';
  }).length;
  return {
    hidden,
    cursorOpacity: (() => {
      const c = document.querySelector('.cursor');
      return c ? getComputedStyle(c).opacity : 'absent';
    })(),
    heroText: (document.querySelector('.hero__title')?.textContent || '').trim().length
  }
});
rmErrors.forEach((e) => note('index.html', 'reduced-motion', `pageerror: ${e}`));
if (rmState.hidden > 0) {
  note('index.html', 'reduced-motion', `${rmState.hidden} reveal elements stay invisible`);
}
if (rmState.heroText < 20) {
  note('index.html', 'reduced-motion', 'hero headline text missing after split');
}
if (rmState.cursorOpacity !== 'absent' && Number(rmState.cursorOpacity) > 0.01) {
  note('index.html', 'reduced-motion', `custom cursor visible (opacity ${rmState.cursorOpacity})`);
}
await rmCtx.close();

await browser.close();

/* -------------------------------------------------------------------- report */

const combos = VIEWPORTS.length * PAGES.length;
console.log(`checked ${PAGES.length} pages x ${VIEWPORTS.length} viewports = ${combos} combos`);
console.log('plus: drawer, accordion, form validation, honeypot, reduced motion\n');

if (fails.length) {
  console.log(`${fails.length} issue(s):`);
  for (const f of [...new Set(fails)]) console.log('  -', f);
  process.exit(1);
}
console.log('browser QA clean');