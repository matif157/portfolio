# Raisatravel B2B Website

The Raisatravel B2B website — a dark, futuristic corporate site for Raisatravel, built to
show how the company supplies skilled workforce to large international corporate groups,
with a strong focus on the Gulf & Middle East.

## What it includes

- Six pages built for corporate employers, not job seekers
- Real company data: the 1997 history, OEP licence, leadership and client list
- The company's actual five-stage deployment procedure, plus the four-stage client
  screening gate
- A working enquiry form backed by a serverless endpoint (validation, honeypot, rate limit)
- Live interactions: reveal animations, counters, a mobile drawer, a marquee, a canvas
  particle field, a scroll-driven process rail and accordions
- Direct WhatsApp, CEO and HR routes everywhere, so the form is never the only way in
- Responsive from 390px up, with reduced-motion support

## Pages

| Page | File | What it does |
| --- | --- | --- |
| Home | `index.html` | Hero, client marquee, procedure preview, markets, sectors |
| About | `about.html` | Company record, journey timeline, leadership, credentials |
| Workforce Solutions | `workforce-solutions.html` | Services, engagement model, sector coverage |
| Vetting Process | `vetting-process.html` | The five stages in full, deliverables, FAQ |
| Markets & Industries | `clients-industries.html` | Destination markets, client names, sectors |
| Contact | `contact.html` | Enquiry form, direct contacts, office details |

## Running it locally

```bash
npm install
npm run dev
```

Then open `http://localhost:5173`.

## Building for production

```bash
npm run build
```

The built site lands in `dist/`.

### The production origin

Canonical tags, `og:url`, `robots.txt` and `sitemap.xml` are all generated from a single
value, `SITE_ORIGIN`, which defaults to `https://raisatravel.com`. Override it when the
build is served from somewhere else:

```bash
SITE_ORIGIN=https://raisatravel-b2b.vercel.app npm run build
```

This matters more than it looks. A canonical pointing at a host that does not actually
serve the build tells search engines the real page lives somewhere else, which quietly
suppresses indexing — and it is the kind of mismatch nobody notices because the site still
loads fine.

## How the code is put together

```
partials/        header, mobile drawer and footer, injected at build time
css/             variables -> base -> layout -> components -> sections -> motion
js/main.js       interaction engine (progressive enhancement, no framework)
js/enquiry.js    enquiry form controller
api/enquiry.js   Vercel serverless endpoint
scripts/         the checks below
```

`vite.config.js` includes a small transform that swaps `<!--#include name-->` for
`partials/<name>.html` in both dev and build, so the header and footer live in one place
instead of six.

## Checks

```bash
npm run verify      # build + static audit (links, classes, vars, a11y basics)
npm run qa:api      # real form -> real endpoint, end to end
npm run qa:browser  # 6 pages x 4 viewports, drawer, accordion, form, reduced motion
npm run qa:contrast # every run of text against its real painted surface
npm run qa:strict   # ids, headings, landmarks, 360px overflow, CLS, fonts, keyboard
npm run qa:pixel    # proves each headline actually paints pixels
npm run qa          # verify + api integration
```

`qa:browser`, `qa:contrast`, `qa:strict` and `qa:pixel` need a server running:

```bash
npm run build && npm run preview -- --port 4173
```

Notes on how these work, since these checks are easy to get wrong:

- Tap targets are measured by **hit-testing** rather than box size, so links that expand
  their hit area with a pseudo-element count as passing.
- `qa:contrast` blanks the text before sampling the surface behind it, so it works on
  gradients and glass panels, and forces the reveal animations open first — otherwise
  elements that have not animated in are still transparent and sample as page background.
  Fixed-position chrome is hidden while sampling, because the floating WhatsApp button
  otherwise becomes the "background" of whatever heading it happens to overlap. Its own
  contrast is asserted separately, in isolation.
- `qa:pixel` exists because every other check reads the DOM. A headline can be present,
  sized and styled correctly in markup while being translated entirely out of view, and
  still pass all of them. That is exactly what shipped once: a 707px tall hero with
  almost no text in it. This one measures how much of the heading is actually painted.

Every browser check takes a base URL, so the deployed site can be checked rather than just
the local build:

```bash
node scripts/browser-qa.mjs  https://raisatravel-b2b.vercel.app
node scripts/contrast.mjs    https://raisatravel-b2b.vercel.app
node scripts/strict-audit.mjs https://raisatravel-b2b.vercel.app
node scripts/pixel-qa.mjs    https://raisatravel-b2b.vercel.app
node scripts/api-integration.mjs https://raisatravel-b2b.vercel.app
```

## Contact details used

- CEO: `ceo@raisatravel.com`
- HR: `hr@raisatravel.com`
- General: `info@raisatravel.com`
- WhatsApp: `https://wa.me/923360007633`
- Office: `+92 41 2637032`

All are plain `mailto:` / `tel:` / `wa.me` links. No third-party chat widget, so nothing
breaks if a service changes.

## The enquiry form

`api/enquiry.js` runs server-side only; no secret reaches the browser. It validates and
length-caps every field, absorbs bot traffic with a honeypot, a submit-timing check and a
per-IP limit of 5 requests per 10 minutes.

To have enquiries emailed, set these in Vercel (never in code):

| Variable | Required | Purpose |
| --- | --- | --- |
| `RESEND_API_KEY` | optional | Enables email delivery via Resend |
| `ENQUIRY_TO` | optional | Where enquiries are delivered (defaults to the CEO) |

Without `RESEND_API_KEY` the endpoint still validates and accepts enquiries and logs them
to the function output, so the form never silently dead-ends. Note that the sender address
in `deliver()` is Resend's onboarding address and must be changed to a verified domain
before real delivery works.

## Tech

- HTML5, CSS3, vanilla JavaScript (ES modules)
- Vite
- CSS custom properties, `clamp()`, grid and flexbox
- Inline SVG
- Playwright and pngjs, dev-only, for the checks

## Deploying

```bash
npm i -g vercel
vercel --prod
```

Or connect the repository in the Vercel dashboard and let it build on every push.

### Commits must have a real author address

Deployments fail with `BLOCKED` and this message:

> The deployment was blocked because Vercel couldn't find a Git account for the commit author.

Vercel resolves the commit author's email address to a Git account before it will build. An
address that belongs to no GitHub account — a machine-local one like
`user@Husayns-MacBook-Air.local` is the usual culprit — is rejected.

That failure looks like a queue problem rather than a rejected commit: the build sits at
"Building…", never starts, reports no logs and no assigned cores, and the CLI just hangs.
Check the real state with `vercel ls`, which reports `BLOCKED`.

If git has no identity configured it falls back to the machine-local address, so this hits
every commit. Set one in the repository:

```bash
git config user.email "143295911+matif157@users.noreply.github.com"
git config user.name "Husayn"
```

## Live website

[https://raisatravel-b2b.vercel.app](https://raisatravel-b2b.vercel.app)

## Built by

[matif157](https://github.com/matif157)

## Notes

- No team photos or photo grids — leadership is presented as text expertise cards
- No active job vacancies
- Built strictly for B2B corporate clients
- Private repository