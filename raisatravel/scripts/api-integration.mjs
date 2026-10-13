/**
 * End-to-end form test.
 *
 *   node scripts/api-integration.mjs
 *
 * Serves dist/ and mounts the real api/enquiry.js handler on /api/enquiry, then
 * drives the actual contact form in a browser. This is what proves the client
 * and server agree on field names, payload shape and response format — neither
 * the static audit nor the browser QA can catch a mismatch between them.
 */

import http from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { chromium } from 'playwright';
import handler from '../api/enquiry.js';

const DIST = new URL('../dist/', import.meta.url).pathname;
const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.json': 'application/json'
};

const server = http.createServer(async (req, res) => {
  if (req.url.startsWith('/api/enquiry')) {
    let body = '';
    for await (const chunk of req) body += chunk;
    req.body = body;
    /* adapt node's ServerResponse to the shape the handler expects */
    res.status = (code) => {
      res.statusCode = code;
      return res;
    };
    res.json = (obj) => {
      res.setHeader('Content-Type', 'application/json');
      res.end(JSON.stringify(obj));
      return res;
    };
    return handler(req, res);
  }

  const clean = normalize(decodeURIComponent(req.url.split('?')[0]));
  const file = join(DIST, clean === '/' ? 'index.html' : clean);
  try {
    const data = await readFile(file);
    res.setHeader('Content-Type', TYPES[extname(file)] || 'application/octet-stream');
    res.end(data);
  } catch {
    res.statusCode = 404;
    res.end('not found');
  }
});

await new Promise((r) => server.listen(4180, r));
const BASE = process.argv[2] || 'http://localhost:4180';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const fails = [];
const check = (ok, msg) => {
  if (!ok) fails.push(msg);
};

await page.goto(`${BASE}/contact.html`, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(800);

/* ---- 1. a real submission reaches the server and succeeds ---------------- */
await page.fill('#f-name', 'Imran Sheikh');
await page.fill('#f-email', 'imran@company.pk');
await page.fill('#f-phone', '+92 300 1234567');
await page.fill('#f-company', 'Sheikh Construction');
await page.selectOption('#f-country', 'Saudi Arabia');
await page.selectOption('#f-sector', 'Construction & Infrastructure');
await page.fill('#f-headcount', '40');
await page.fill(
  '#f-message',
  'We need 40 civil workers for a Riyadh project starting in March. Please advise on lead time.'
);

const stampAge = await page.evaluate(
  () => Date.now() - Number(document.querySelector('[name="t"]').value)
);
check(stampAge > 900, `hidden t stamp should predate the submit, was ${stampAge}ms`);

await page.click('button[type="submit"]');
await page.waitForSelector('[data-formstatus].is-shown', { timeout: 10000 });

const status = await page.evaluate(() => ({
  cls: document.querySelector('[data-formstatus]').className,
  text: document.querySelector('[data-formstatus]').textContent.trim(),
  busy: document.querySelector('button[type="submit"]').getAttribute('aria-busy'),
  disabled: document.querySelector('button[type="submit"]').disabled
}));

check(status.cls.includes('formstatus--ok'), `expected success banner, got "${status.cls}"`);
check(/REQ-[A-Z0-9]+/.test(status.text), `success message should include a reference: "${status.text}"`);
check(status.busy === null, 'aria-busy should be cleared after submit');
check(status.disabled === false, 'submit button should be re-enabled');

const cleared = await page.evaluate(() => ({
  name: document.querySelector('#f-name').value,
  message: document.querySelector('#f-message').value,
  invalid: document.querySelectorAll('.field.is-invalid').length
}));
check(cleared.name === '', 'form should reset after a successful submit');
check(cleared.message === '', 'message field should reset');
check(cleared.invalid === 0, `no fields should stay invalid, saw ${cleared.invalid}`);

/* ---- 2. the server validates rather than accepting junk ------------------ */
const post = (body) =>
  page.evaluate(
    (b) =>
      fetch('/api/enquiry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(b)
      }).then((r) => r.json().then((j) => ({ status: r.status, body: j }))),
    body
  );

const bad = await post({ name: 'A', email: 'nope', phone: 'xx', message: 'hi' });
check(bad.status === 422, `invalid payload should be 422, got ${bad.status}`);
check(bad.body.errors?.name, 'name error should be reported');
check(bad.body.errors?.email, 'email error should be reported');
check(bad.body.errors?.phone, 'phone error should be reported');
check(bad.body.errors?.message, 'message error should be reported');

/* ---- 3. honeypot is swallowed silently ----------------------------------- */
const spam = await post({
  name: 'Bot Name',
  email: 'bot@spam.example',
  message: 'buy cheap backlinks now',
  website: 'http://spam.example'
});
check(spam.status === 200, `honeypot should return 200, got ${spam.status}`);
check(spam.body.ok === true, 'honeypot should look like success');
check(!spam.body.ref, 'honeypot submission must not produce a reference');

/* ---- 4. an immediate resubmit is rate limited --------------------------- */
const codes = [];
for (let i = 0; i < 7; i++) {
  const r = await post({
    name: 'Imran Sheikh',
    email: 'imran@company.pk',
    message: 'Repeat submission to exercise the rate limiter.',
    t: Date.now() - 9000
  });
  codes.push(r.status);
}
check(codes.includes(429), `expected a 429 within 7 posts, got ${codes.join(',')}`);

/* ---- 5. GET is rejected -------------------------------------------------- */
const getRes = await page.evaluate(() =>
  fetch('/api/enquiry').then((r) => ({ status: r.status, allow: r.headers.get('allow') }))
);
check(getRes.status === 405, `GET should be 405, got ${getRes.status}`);
check(getRes.allow === 'POST', 'GET response should advertise Allow: POST');

await browser.close();
await new Promise((r) => server.close(r));

console.log('form/API integration checks:');
console.log('  success path, validation, honeypot, rate limit, method guard');
if (fails.length) {
  console.log(`\n${fails.length} failure(s):`);
  for (const f of fails) console.log('  -', f);
  process.exit(1);
}
console.log('\nintegration clean');