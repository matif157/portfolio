import { defineConfig } from 'vite';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const partials = resolve(root, 'partials');

/**
 * The one place the production origin is defined.
 *
 * Canonical tags, og:url, robots.txt and sitemap.xml all derive from this. It
 * used to be hard-coded in fourteen places, which meant repointing the domain
 * meant hunting for them - and a canonical pointing at a host that does not
 * actually serve the build is quietly harmful to indexing.
 *
 * Override per environment, e.g. SITE_ORIGIN=https://raisatravel-b2b.vercel.app
 */
const SITE_ORIGIN = (process.env.SITE_ORIGIN || 'https://raisatravel.com').replace(/\/+$/, '');

const PAGES = [
  { file: 'index.html', path: '/', priority: '1.0' },
  { file: 'about.html', path: '/about', priority: '0.8' },
  { file: 'workforce-solutions.html', path: '/workforce-solutions', priority: '0.8' },
  { file: 'vetting-process.html', path: '/vetting-process', priority: '0.8' },
  { file: 'clients-industries.html', path: '/clients-industries', priority: '0.8' },
  { file: 'contact.html', path: '/contact', priority: '0.7' }
];

/**
 * Replaces <!--#include name--> with partials/<name>.html at build AND dev time.
 * Keeps the header/footer in exactly one place so the six pages can't drift apart.
 */
function htmlPartials() {
  return {
    name: 'raisatravel-html-partials',
    enforce: 'pre',
    transformIndexHtml: {
      order: 'pre',
      handler(html, ctx) {
        if (!ctx.path || !ctx.path.endsWith('.html')) return html;
        return html.replace(/<!--#include\s+([\w-]+)\s*-->/g, (_m, name) => {
          const file = resolve(partials, `${name}.html`);
          if (!existsSync(file)) {
            this.error(`Partial "${name}.html" not found for ${ctx.path}`);
            return '';
          }
          return readFileSync(file, 'utf8').trimEnd();
        });
      }
    }
  };
}

/**
 * Points canonical and og:url at SITE_ORIGIN, and generates robots.txt and
 * sitemap.xml from the same value so the three can never disagree.
 */
function siteOrigin() {
  const canonical = 'https://raisatravel.com';
  return {
    name: 'raisatravel-site-origin',
    transformIndexHtml: {
      order: 'pre',
      handler(html, ctx) {
        if (!ctx.path || !ctx.path.endsWith('.html')) return html;
        if (SITE_ORIGIN === canonical) return html;
        return html.split(canonical).join(SITE_ORIGIN);
      }
    },
    generateBundle() {
      this.emitFile({
        type: 'asset',
        fileName: 'robots.txt',
        source: `User-agent: *\nAllow: /\n\nSitemap: ${SITE_ORIGIN}/sitemap.xml\n`
      });

      const urls = PAGES.map(
        ({ path, priority }) =>
          `  <url>\n    <loc>${SITE_ORIGIN}${path}</loc>\n` +
          `    <changefreq>monthly</changefreq>\n    <priority>${priority}</priority>\n  </url>`
      ).join('\n');

      this.emitFile({
        type: 'asset',
        fileName: 'sitemap.xml',
        source:
          `<?xml version="1.0" encoding="UTF-8"?>\n` +
          `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`
      });
    }
  };
}

export default defineConfig({
  plugins: [siteOrigin(), htmlPartials()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    assetsInlineLimit: 2048,
    rollupOptions: {
      input: {
        main: resolve(root, 'index.html'),
        about: resolve(root, 'about.html'),
        vetting: resolve(root, 'vetting-process.html'),
        solutions: resolve(root, 'workforce-solutions.html'),
        industries: resolve(root, 'clients-industries.html'),
        contact: resolve(root, 'contact.html'),
        notFound: resolve(root, '404.html')
      }
    }
  }
});