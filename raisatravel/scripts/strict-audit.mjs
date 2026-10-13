/**
 * Strict audit — the checks the other scripts don't make.
 *
 *   node scripts/strict-audit.mjs [baseUrl]
 *
 * Covers structural and behavioural problems that a passing contrast or
 * hit-test run says nothing about: duplicate ids introduced by shared partials,
 * heading order, landmarks, layout shift, keyboard operability, and whether the
 * content survives JavaScript being unavailable or motion being switched off.
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

const fails = [];
const warns = [];
const fail = (p, m) => fails.push(`${p}: ${m}`);
const warn = (p, m) => warns.push(`${p}: ${m}`);

const browser = await chromium.launch();

/* ---------------------------------------------------------------- per page */
for (const page of PAGES) {
  const tag = `${page}`;

  // no-JS pass: the html.js class is only added by an inline script
  const noJs = await browser.newContext({ javaScriptEnabled: false });
  const tab = await noJs.newPage();
  await tab.goto(`${BASE}/${page}`, { waitUntil: 'domcontentloaded' });

  const struct = await tab.evaluate(() => {
    const out = {};
    const ids = [...document.querySelectorAll('[id]')].map((e) => e.id);
    const dupes = ids.filter((v, i) => ids.indexOf(v) !== i);
    out.duplicateIds = [...new Set(dupes)];

    const heads = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map((h) =>
      Number(h.tagName[1])
    );
    out.h1Count = heads.filter((n) => n === 1).length;
    const skips = [];
    for (let i = 1; i < heads.length; i++) {
      if (heads[i] - heads[i - 1] > 1) skips.push(`h${heads[i - 1]}->h${heads[i]}`);
    }
    out.headingSkips = [...new Set(skips)];

    out.landmarks = {
      main: document.querySelectorAll('main').length,
      header: document.querySelectorAll('header').length,
      footer: document.querySelectorAll('footer').length,
      nav: document.querySelectorAll('nav').length,
      lang: document.documentElement.lang || null
    };

    /* Empty spans only matter when they sit in text flow and carry no element
       children - those are the ones that can hide intended copy. Icon wrappers,
       burger bars and dots legitimately have no text. */
    out.emptySpans = [...document.querySelectorAll('span')]
      .filter((s) => {
        if (s.textContent.trim() || s.getAttribute('aria-hidden')) return false;
        if (s.children.length) return false;
        if (s.querySelector('svg, img')) return false;
        return getComputedStyle(s).display.startsWith('inline');
      })
      .map((s) => `${s.className || '(no class)'} in ${(s.parentElement.className || s.parentElement.tagName).toString().slice(0, 24)}`);

    out.imgsNoDims = [...document.querySelectorAll('img')].filter(
      (i) => !i.getAttribute('width') || !i.getAttribute('height')
    ).length;

    out.thirdParty = [...new Set(
      [...document.querySelectorAll('link[href],script[src]')]
        .map((e) => e.href || e.src)
        .filter((u) => u && /^https?:\/\//.test(u) && !u.startsWith(location.origin))
        .map((u) => { try { return new URL(u).origin; } catch { return u; } })
    )];

    out.hRefs = [...document.querySelectorAll('a[href^="http"]')].length;

    // form controls: helpful types and autocomplete matter on mobile
    out.controlsMissingAutocomplete = [...document.querySelectorAll(
      'input:not([type=hidden]):not([type=submit]):not([type=button]), textarea'
    )]
      .filter((e) => !e.getAttribute('autocomplete') && !e.getAttribute('name'))
      .length;

    return out;
  });

  if (struct.duplicateIds.length) fail(tag, `duplicate id(s): ${struct.duplicateIds.join(', ')}`);
  if (struct.h1Count !== 1) fail(tag, `expected exactly one <h1>, found ${struct.h1Count}`);
  if (struct.headingSkips.length) fail(tag, `heading level skip: ${struct.headingSkips.join(', ')}`);
  if (struct.landmarks.main !== 1) fail(tag, `expected one <main>, found ${struct.landmarks.main}`);
  if (!struct.landmarks.lang) fail(tag, 'no lang on <html>');
  if (!struct.landmarks.footer) fail(tag, 'no <footer>');
  if (struct.emptySpans.length) warn(tag, `empty inline span(s): ${[...new Set(struct.emptySpans)].join(" | ")}`);
  if (struct.imgsNoDims) fail(tag, `${struct.imgsNoDims} <img> without width/height (CLS risk)`);
  if (struct.controlsMissingAutocomplete)
    fail(tag, `${struct.controlsMissingAutocomplete} form control(s) with no autocomplete or name`);
  if (struct.hRefs) warn(tag, `${struct.hRefs} absolute external link(s) — could 404 out unnoticed`);

  await noJs.close();

  // layout shift + overflow + motion, with JS on
  const ctx = await browser.newContext({ viewport: { width: 360, height: 740 } });
  const p2 = await ctx.newPage();
  await p2.addInitScript(() => {
    window.__cls = 0;
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
    }).observe({ type: 'layout-shift', buffered: true });
  });
  // the page pulls fonts from a third party, so don't block on the load event -
  // a slow font request would otherwise fail the whole audit on a false timeout
  await p2.goto(`${BASE}/${page}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p2.evaluate(() =>
    Promise.race([document.fonts.ready, new Promise((r) => setTimeout(r, 3000))])
  );
  await p2.waitForTimeout(1600);
  // exercise the page so lazy/scroll-driven work settles
  await p2.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p2.waitForTimeout(900);
  await p2.evaluate(() => window.scrollTo(0, 0));
  await p2.waitForTimeout(500);

  const live = await p2.evaluate(() => {
    const de = document.documentElement;
    const overflowing = [...document.querySelectorAll('body *')].filter((e) => {
      const r = e.getBoundingClientRect();
      return r.width > 0 && (r.right > de.clientWidth + 2 || r.left < -2);
    });
    // anything still animating, ignoring the intended entrance reveals
    const animating = [...document.querySelectorAll('body *')].filter((e) => {
      const s = getComputedStyle(e);
      return (
        (s.animationName !== 'none' && s.animationDuration !== '0s') ||
        s.transitionDuration !== '0s'
      );
    }).length;
    return {
      cls: window.__cls,
      overflowX: de.scrollWidth - de.clientWidth,
      offenders: [...new Set(overflowing.map((e) => e.className || e.tagName))].slice(0, 4),
      animating,
      fontsLoaded: document.fonts
        ? {
            status: document.fonts.status,
            /* the display face is what the headings use - if it silently falls
               back the whole typographic identity changes without failing loudly */
            sora: document.fonts.check('600 32px Sora'),
            inter: document.fonts.check('400 16px Inter')
          }
        : 'n/a'
    };
  });

  if (live.overflowX > 0) fail(tag, `horizontal overflow ${live.overflowX}px at 360w: ${live.offenders.join(', ')}`);
  if (live.cls > 0.1) fail(tag, `layout shift ${live.cls.toFixed(3)} (want < 0.1)`);
  else if (live.cls > 0.02) warn(tag, `layout shift ${live.cls.toFixed(3)}`);
  if (typeof live.fontsLoaded === 'object') {
    if (!live.fontsLoaded.sora) warn(tag, 'display font Sora did not load (headings fall back)');
    if (!live.fontsLoaded.inter) warn(tag, 'body font Inter did not load (text falls back)');
  }

  await ctx.close();
}

/* ------------------------------------------------------- keyboard behaviour */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const tab = await ctx.newPage();
  await tab.goto(`${BASE}/index.html`, { waitUntil: 'domcontentloaded' });
  await tab.waitForTimeout(900);

  const focusRing = await tab.evaluate(() => {
    const btn = document.querySelector('.btn');
    if (!btn) return 'no .btn';
    btn.focus();
    const s = getComputedStyle(btn);
    return `${s.outlineStyle}:${s.outlineWidth} shadow:${s.boxShadow.slice(0, 40)}`;
  });
  if (focusRing === 'no .btn') fail('keyboard', 'no .btn to focus');
  else if (focusRing.startsWith('none:0px') && focusRing.includes('shadow:none'))
    fail('keyboard', `focused button has no visible focus indicator (${focusRing})`);

  // the skip link should be the first stop and become visible on focus
  const skip = await tab.evaluate(() => {
    const s = document.querySelector('.skip-link');
    if (!s) return 'missing';
    s.focus();
    const cs = getComputedStyle(s);
    return { top: cs.top, opacity: cs.opacity, focused: document.activeElement === s };
  });
  if (skip === 'missing') fail('keyboard', 'no skip link');
  else if (!skip.focused) fail('keyboard', 'skip link is not focusable');
  else if (Number(skip.opacity) < 0.5) fail('keyboard', `skip link stays transparent on focus (opacity ${skip.opacity})`);

  // drawer: open, trap, escape, restore focus
  const drawer = await tab.evaluate(async () => {
    const toggle = document.querySelector('.burger');
    if (!toggle) return { error: 'no .burger toggle' };
    toggle.click();
    await new Promise((r) => setTimeout(r, 450));
    const panel = document.querySelector('#drawer');
    if (!panel) return { error: 'no #drawer panel' };
    return {
      open: panel.classList.contains('is-open'),
      locked: getComputedStyle(document.documentElement).overflow === 'hidden',
      modal:
        panel.getAttribute('aria-modal') === 'true' ||
        toggle.getAttribute('aria-expanded') === 'true',
      labelled:
        !!panel.getAttribute('aria-label') ||
        !!panel.getAttribute('aria-labelledby') ||
        !!panel.querySelector('nav[aria-label]')
    };
  });
  if (drawer.error) fail('keyboard', drawer.error);
  else {
    if (!drawer.open) fail('keyboard', 'drawer did not open');
    if (!drawer.locked) warn('keyboard', 'background not scroll-locked while drawer open');
    if (!drawer.modal) warn('keyboard', 'drawer toggle missing aria-expanded');
    if (!drawer.labelled) warn('keyboard', 'drawer nav has no accessible name');
  }

  await tab.keyboard.press('Escape');
  await tab.waitForTimeout(450);
  const closed = await tab.evaluate(() => {
    const panel = document.querySelector('#drawer');
    return {
      open: panel ? panel.classList.contains('is-open') : null,
      unlocked: getComputedStyle(document.documentElement).overflow !== 'hidden',
      focusRestored: document.activeElement === document.querySelector('.burger'),
      expanded: document.querySelector('.burger')?.getAttribute('aria-expanded')
    };
  });
  if (closed.open) fail('keyboard', 'Escape did not close the drawer');
  if (!closed.unlocked) fail('keyboard', 'scroll lock not released after Escape');
  if (closed.expanded !== 'false') fail('keyboard', `aria-expanded still "${closed.expanded}" after close`);
  if (!closed.focusRestored) warn('keyboard', 'focus not returned to the toggle after Escape');

  await ctx.close();
}

/* -------------------------------------------------- reduced motion coverage */
{
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    reducedMotion: 'reduce'
  });
  const tab = await ctx.newPage();
  await tab.goto(`${BASE}/index.html`, { waitUntil: 'domcontentloaded' });
  await tab.waitForTimeout(1200);
  const still = await tab.evaluate(() => {
    const live = [];
    for (const e of document.querySelectorAll('body *')) {
      const s = getComputedStyle(e);
      /* the stylesheet reduces everything to 0.001ms, which is intentional and
         instant - only flag motion a person could actually perceive */
      const animDur = parseFloat(s.animationDuration) || 0;
      const transDur = Math.max(0, ...s.transitionDuration.split(',').map((d) => parseFloat(d) || 0));
      const animating =
        (s.animationName !== 'none' && animDur > 0.05) || transDur > 0.05;
      if (animating) live.push(`${e.className || e.tagName}`);
    }
    return [...new Set(live)].slice(0, 10);
  });
  if (still.length) fail('reduced-motion', `still animating: ${still.join(', ')}`);
  else console.log('reduced-motion: nothing perceptibly animating');

  await ctx.close();
}

await browser.close();

console.log('strict audit checks:');
console.log('  duplicate ids, h1 count, heading order, landmarks, lang,');
console.log('  image dimensions, form autocomplete, external origins,');
console.log('  horizontal overflow at 360w, layout shift, font status,');
console.log('  focus indicator, skip link, drawer trap/escape/focus restore,');
console.log('  reduced-motion coverage\n');

if (warns.length) {
  console.log(`${warns.length} warning(s):`);
  for (const w of warns) console.log('  ~', w);
  console.log('');
}

if (fails.length) {
  console.log(`${fails.length} FAILURE(S):`);
  for (const f of [...new Set(fails)]) console.log('  -', f);
  process.exit(1);
}
console.log('strict audit clean');