/* ==========================================================================
   Raisatravel — corporate enquiry endpoint (Vercel serverless)

   Design notes
   ------------
   * No secret ever reaches the browser. This file runs server-side only.
   * Every field is validated and length-capped before anything is stored.
   * A honeypot field plus per-IP rate limiting absorb most bot traffic.
   * If RESEND_API_KEY is set, the enquiry is also emailed to the agency.
     Without it the enquiry is still accepted and logged, so the form never
     silently dead-ends during local development.

   Env vars (set in Vercel, never in code):
     RESEND_API_KEY   optional, transactional email key
     ENQUIRY_TO       optional, where enquiries are delivered
   ========================================================================== */

const RATE_LIMIT = 5; // requests
const RATE_WINDOW = 10 * 60 * 1000; // per 10 minutes

/* In-memory store. Resets on cold start, which is fine for a site this size —
   it raises the cost of casual abuse without needing a database. */
const hits = new Map();

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;
const PHONE_RE = /^[+()\d][\d\s()-]{5,19}$/;

/* keep the store from growing without bound on a long-lived instance */
const sweep = () => {
  const now = Date.now();
  for (const [ip, rec] of hits) {
    if (now - rec.start > RATE_WINDOW) hits.delete(ip);
  }
};

function rateLimited(ip) {
  if (hits.size > 5000) sweep();
  const now = Date.now();
  const rec = hits.get(ip);
  if (!rec || now - rec.start > RATE_WINDOW) {
    hits.set(ip, { start: now, n: 1 });
    return false;
  }
  rec.n += 1;
  return rec.n > RATE_LIMIT;
}

const clientIp = (req) =>
  (req.headers['x-forwarded-for'] || '').split(',')[0].trim() ||
  req.headers['x-real-ip'] ||
  'unknown';

const str = (v, max) => (typeof v === 'string' ? v.trim().slice(0, max) : '');

/* Single-line fields: strip control characters and collapse runs of spaces.
   Newlines are removed entirely so a value can never break out of its row. */
const clean = (v, max) =>
  str(v, max)
    .replace(/[\r\n\t]+/g, ' ')
    .replace(/[\x00-\x1f\x7f]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

/* Multi-line field (the requirement body): keeps paragraph breaks because the
   email template renders it with white-space:pre-wrap. */
const cleanBlock = (v, max) =>
  str(v, max)
    .replace(/\r\n?/g, '\n')
    .replace(/[\x00-\x08\x0b-\x1f\x7f]/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();

/* HTML-escapes a value for safe interpolation into the email template.
   Without this, an "&" in a company name would break the markup. */
const esc = (v) =>
  String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

function validate(body) {
  const data = {
    name: clean(body.name, 80),
    email: clean(body.email, 120).toLowerCase(),
    phone: clean(body.phone, 24),
    company: clean(body.company, 120),
    country: clean(body.country, 60),
    sector: clean(body.sector, 60),
    headcount: clean(body.headcount, 20),
    message: cleanBlock(body.message, 2000)
  };

  const errors = {};
  if (data.name.length < 2) errors.name = 'Please tell us your name.';
  if (!EMAIL_RE.test(data.email)) errors.email = 'Please enter a valid work email address.';
  if (data.phone && !PHONE_RE.test(data.phone)) errors.phone = 'Please enter a valid phone number.';
  if (data.message.length < 10)
    errors.message = 'Please add a little more detail (at least 10 characters).';

  return { data, errors, ok: Object.keys(errors).length === 0 };
}

async function deliver(data) {
  const key = process.env.RESEND_API_KEY;
  if (!key) return { sent: false, reason: 'no-key-configured' };

  const to = process.env.ENQUIRY_TO || 'ceo@raisatravel.com';
  const row = (k, v) =>
    `<tr><td style="padding:6px 14px 6px 0;color:#789;font-size:13px">${esc(
      k
    )}</td><td style="padding:6px 0;font-size:14px">${esc(v) || '—'}</td></tr>`;

  const html = `
    <div style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f6f8fb;padding:24px">
      <div style="max-width:620px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e6ebf2">
        <div style="background:#000814;padding:20px 26px">
          <div style="color:#00e0ff;font-size:11px;letter-spacing:.18em;text-transform:uppercase">Corporate enquiry</div>
          <div style="color:#fff;font-size:19px;font-weight:600;margin-top:6px">${esc(data.name)}${
            data.company ? ` · ${esc(data.company)}` : ''
          }</div>
        </div>
        <table style="width:100%;border-collapse:collapse;padding:8px 26px 26px">
          ${row('Email', data.email)}
          ${row('Phone', data.phone)}
          ${row('Country', data.country)}
          ${row('Sector', data.sector)}
          ${row('Headcount needed', data.headcount)}
        </table>
        <div style="padding:0 26px 26px">
          <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#789;margin-bottom:8px">Requirement</div>
          <div style="font-size:14px;line-height:1.7;white-space:pre-wrap">${esc(data.message)}</div>
        </div>
      </div>
    </div>`;

  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      from: 'Raisatravel Website <onboarding@resend.dev>',
      to: [to],
      reply_to: data.email,
      subject: `Corporate enquiry — ${data.company || data.name}${data.sector ? ` (${data.sector})` : ''}`,
      text: [
        `Name: ${data.name}`,
        `Company: ${data.company}`,
        `Email: ${data.email}`,
        `Phone: ${data.phone}`,
        `Country: ${data.country}`,
        `Sector: ${data.sector}`,
        `Headcount: ${data.headcount}`,
        '',
        data.message
      ].join('\n'),
      html
    })
  });

  return { sent: res.ok, status: res.status };
}

export default async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json');

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return res.status(405).json({ ok: false, error: 'Method not allowed.' });
  }

  if (rateLimited(clientIp(req))) {
    return res
      .status(429)
      .json({ ok: false, error: 'Too many messages sent. Please try again in a few minutes.' });
  }

  let body = {};
  try {
    body = typeof req.body === 'string' ? JSON.parse(req.body) : req.body || {};
  } catch {
    return res.status(400).json({ ok: false, error: 'Could not read the request.' });
  }

  /* honeypot — a real user never sees or fills this */
  if (str(body.website, 100)) {
    return res.status(200).json({ ok: true });
  }

  /* Crude timing check: a human cannot complete and submit this under ~900ms.
     Two enquiries in the same millisecond must still be treated as a bot, so
     the guard is >= 0 rather than > 0. */
  const elapsed = Date.now() - Number(body.t || 0);
  if (Number.isFinite(elapsed) && elapsed >= 0 && elapsed < 900) {
    return res.status(200).json({ ok: true });
  }

  const { data, errors, ok } = validate(body);

  if (!ok) {
    return res.status(422).json({ ok: false, errors, error: 'Please check the highlighted fields.' });
  }

  /* Timestamp + random suffix so two enquiries in the same millisecond cannot
     share a reference. */
  const ref = `REQ-${Date.now().toString(36).toUpperCase().slice(-7)}${Math.random()
    .toString(36)
    .slice(2, 5)
    .toUpperCase()}`;
  const delivery = await deliver(data).catch((e) => ({ sent: false, reason: String(e) }));

  console.log('[enquiry]', JSON.stringify({ ref, ...data, delivery }));

  return res.status(200).json({
    ok: true,
    ref,
    delivered: delivery.sent
  });
}