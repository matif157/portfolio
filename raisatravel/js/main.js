/* ==========================================================================
   Raisatravel — interaction engine
   Everything degrades safely: if a container is missing, that module no-ops.
   All motion respects prefers-reduced-motion.
   ========================================================================== */

const mqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const mqHover = window.matchMedia('(hover: hover) and (pointer: fine)');

const reduced = () => mqReduce.matches;
const finePointer = () => mqHover.matches;

const $ = (sel, scope = document) => scope.querySelector(sel);
const $$ = (sel, scope = document) => [...scope.querySelectorAll(sel)];
const clamp = (v, min, max) => Math.min(Math.max(v, min), max);

/* requestAnimationFrame loop that pauses when the tab is hidden */
const raf = (fn) => {
  let id = 0;
  let last = performance.now();
  const tick = (now) => {
    const dt = Math.min(now - last, 64);
    last = now;
    fn(dt, now);
    id = requestAnimationFrame(tick);
  };
  id = requestAnimationFrame(tick);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cancelAnimationFrame(id);
      id = 0;
    } else if (!id) {
      last = performance.now();
      id = requestAnimationFrame(tick);
    }
  });
  return () => cancelAnimationFrame(id);
};

/* ------------------------------------------------------------------ reveal */

function initReveal() {
  const items = $$('[data-reveal]');
  if (!items.length) return;

  if (reduced() || !('IntersectionObserver' in window)) {
    items.forEach((el) => el.classList.add('is-in'));
    return;
  }

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    },
    { rootMargin: '0px 0px -12% 0px', threshold: 0.08 }
  );

  items.forEach((el) => {
    // stagger siblings that declare a group
    const group = el.closest('[data-reveal-group]');
    if (group) {
      const sibs = $$('[data-reveal]', group);
      const i = sibs.indexOf(el);
      if (i > -1) el.style.setProperty('--rd', `${Math.min(i, 8) * 85}ms`);
    }
    io.observe(el);
  });

  /* The staggered per-word headline reveal was removed here.

   It rebuilt each heading's innerHTML into one inline-block span per word,
   which re-wrapped the text differently from plain text below roughly 400px
   and pushed the content underneath it down by a line — a 0.26 layout shift on
   the home page at 360px. Headings now use the same [data-reveal] transition as
   everything else, which only animates opacity and transform and therefore
   cannot move anything. */
}

/* ---------------------------------------------------------------- counters */

function initCounters() {
  const nums = $$('[data-count]');
  if (!nums.length) return;

  const run = (el) => {
    const target = parseFloat(el.dataset.count);
    const suffix = el.dataset.suffix || '';
    if (Number.isNaN(target)) return;

    if (reduced()) {
      el.textContent = target + suffix;
      return;
    }

    const dur = 1500;
    const start = performance.now();
    const step = (now) => {
      const t = clamp((now - start) / dur, 0, 1);
      const eased = 1 - Math.pow(1 - t, 3);
      const val = target % 1 === 0 ? Math.round(target * eased) : (target * eased).toFixed(1);
      el.textContent = val + suffix;
      if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  if (!('IntersectionObserver' in window)) {
    nums.forEach(run);
    return;
  }

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        run(entry.target);
        io.unobserve(entry.target);
      });
    },
    { threshold: 0.5 }
  );
  nums.forEach((el) => io.observe(el));
}

/* ----------------------------------------------------------------- header */

function initHeader() {
  const header = $('.site-header');
  if (!header) return;

  const bar = $('.scroll-progress__bar');
  let ticking = false;

  const update = () => {
    const y = window.scrollY;
    header.classList.toggle('is-stuck', y > 8);

    if (bar) {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      bar.style.transform = `scaleX(${max > 0 ? clamp(y / max, 0, 1) : 0})`;
    }

    const toTop = $('.totop');
    if (toTop) toTop.classList.toggle('is-ready', y > 620);

    ticking = false;
  };

  window.addEventListener(
    'scroll',
    () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(update);
    },
    { passive: true }
  );
  update();
}

/* ------------------------------------------------------------ mobile drawer */

function initDrawer() {
  const burger = $('.burger');
  const drawer = $('.drawer');
  if (!burger || !drawer) return;

  const setOpen = (open) => {
    burger.setAttribute('aria-expanded', String(open));
    drawer.classList.toggle('is-open', open);
    document.documentElement.style.overflow = open ? 'hidden' : '';
    burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };

  burger.addEventListener('click', () => setOpen(burger.getAttribute('aria-expanded') !== 'true'));

  $$('.drawer__link, .drawer__foot a', drawer).forEach((el) =>
    el.addEventListener('click', () => setOpen(false))
  );

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
      setOpen(false);
      burger.focus();
    }
  });

  /* close it if the viewport grows past the breakpoint while it's open */
  const mq = window.matchMedia('(min-width: 1081px)');
  mq.addEventListener('change', (e) => e.matches && setOpen(false));
}

/* ------------------------------------------------------------- custom cursor */

function initCursor() {
  const dot = $('.cursor');
  if (!dot || !finePointer() || reduced()) return;

  let x = window.innerWidth / 2;
  let y = window.innerHeight / 2;
  let cx = x;
  let cy = y;

  window.addEventListener(
    'pointermove',
    (e) => {
      x = e.clientX;
      y = e.clientY;
      dot.classList.add('is-on');
    },
    { passive: true }
  );

  document.addEventListener('pointerleave', () => dot.classList.remove('is-on'));

  raf((dt) => {
    const k = 1 - Math.pow(0.0015, dt / 1000);
    cx += (x - cx) * k;
    cy += (y - cy) * k;
    dot.style.translate = `${cx}px ${cy}px`;
  });

  const grow = () => dot.classList.add('is-link');
  const shrink = () => dot.classList.remove('is-link');
  $$('a, button, [data-cursor="grow"]').forEach((el) => {
    el.addEventListener('pointerenter', grow);
    el.addEventListener('pointerleave', shrink);
  });
}

/* ---------------------------------------------------------------- magnetic */

function initMagnetic() {
  const els = $$('[data-magnetic]');
  if (!els.length || !finePointer() || reduced()) return;

  els.forEach((el) => {
    const strength = parseFloat(el.dataset.magnetic) || 0.28;

    el.addEventListener('pointermove', (e) => {
      const r = el.getBoundingClientRect();
      const dx = e.clientX - (r.left + r.width / 2);
      const dy = e.clientY - (r.top + r.height / 2);
      el.style.transform = `translate(${dx * strength}px, ${dy * strength * 0.65}px)`;
    });

    el.addEventListener('pointerleave', () => {
      el.style.transform = '';
    });
  });
}

/* -------------------------------------------------------------------- tilt */

function initTilt() {
  const els = $$('[data-tilt]');
  if (!els.length || !finePointer() || reduced()) return;

  els.forEach((el) => {
    const max = parseFloat(el.dataset.tilt) || 7;

    el.addEventListener('pointermove', (e) => {
      const r = el.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width;
      const py = (e.clientY - r.top) / r.height;
      el.style.setProperty('--gx', `${px * 100}%`);
      el.style.setProperty('--gy', `${py * 100}%`);
      el.style.transform = `perspective(900px) rotateY(${(px - 0.5) * max}deg) rotateX(${
        (0.5 - py) * max
      }deg) translateY(-4px)`;
    });

    el.addEventListener('pointerleave', () => {
      el.style.transform = '';
    });
  });
}

/* ----------------------------------------------------------------- marquee */

/* Duplicates the track content once so the -50% keyframe loops seamlessly. */
function initMarquee() {
  $$('.marquee').forEach((m) => {
    const track = $('.marquee__track', m);
    if (!track || track.dataset.cloned) return;
    track.dataset.cloned = '1';
    track.innerHTML += track.innerHTML;
  });
}

/* --------------------------------------------------------------- text scramble */

function initScramble() {
  const els = $$('[data-scramble]');
  if (!els.length) return;

  const GLYPHS = '!<>-_\\/[]{}—=+*^?#________';

  els.forEach((el) => {
    const original = el.textContent;
    if (reduced() || !finePointer()) return;

    let frame = 0;
    const io = new IntersectionObserver(
      (entries) => {
        if (!entries.some((e) => e.isIntersecting)) return;
        io.disconnect();
        const len = original.length;
        const total = 22;

        const tick = () => {
          let out = '';
          for (let i = 0; i < len; i++) {
            if (original[i] === ' ') {
              out += ' ';
            } else if (frame / total >= i / len) {
              out += original[i];
            } else {
              out += GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
            }
          }
          el.textContent = out;
          if (frame++ < total) requestAnimationFrame(tick);
          else el.textContent = original;
        };
        tick();
      },
      { threshold: 0.6 }
    );
    io.observe(el);
  });
}

/* ------------------------------------------------------------------- canvas */

function initCanvas() {
  const cv = $('canvas[data-network]');
  if (!cv) return;
  if (reduced()) return;

  const ctx = cv.getContext('2d', { alpha: true });
  if (!ctx) return;

  const host = cv.parentElement;
  let w = 0;
  let h = 0;
  let dpr = 1;
  let pts = [];

  const build = () => {
    const rect = host.getBoundingClientRect();
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = rect.width;
    h = rect.height;
    cv.width = Math.round(w * dpr);
    cv.height = Math.round(h * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    /* density scales with area but stays sane on phones */
    const density = Math.round((w * h) / 15000);
    const count = clamp(density, 26, 78);
    pts = Array.from({ length: count }, () => ({
      x: Math.random() * w,
      y: Math.random() * h,
      vx: (Math.random() - 0.5) * 0.22,
      vy: (Math.random() - 0.5) * 0.22,
      r: Math.random() * 1.5 + 0.7
    }));
  };

  build();

  const ro = new ResizeObserver(build);
  ro.observe(host);

  let visible = true;
  const io = new IntersectionObserver(([e]) => (visible = e.isIntersecting));
  io.observe(cv);

  raf((dt) => {
    if (!visible) return;
    ctx.clearRect(0, 0, w, h);

    const linkDist = Math.min(140, w / 6);

    for (let i = 0; i < pts.length; i++) {
      const p = pts[i];
      p.x += p.vx * (dt / 16);
      p.y += p.vy * (dt / 16);

      if (p.x < 0 || p.x > w) p.vx *= -1;
      if (p.y < 0 || p.y > h) p.vy *= -1;
      p.x = clamp(p.x, 0, w);
      p.y = clamp(p.y, 0, h);

      for (let j = i + 1; j < pts.length; j++) {
        const q = pts[j];
        const dx = p.x - q.x;
        const dy = p.y - q.y;
        const d = Math.hypot(dx, dy);
        if (d < linkDist) {
          ctx.strokeStyle = `rgba(0,224,255,${(1 - d / linkDist) * 0.16})`;
          ctx.lineWidth = 1;
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.stroke();
        }
      }

      ctx.fillStyle = 'rgba(0,224,255,0.5)';
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fill();
    }
  });
}

/* ------------------------------------------------------------- process rail */

function initRail() {
  const rail = $('.rail');
  const fill = $('.rail__fill', rail || document);
  const rows = $$('.process__row', rail || document);
  if (!rail || !fill || !rows.length) return;

  const sync = () => {
    const box = rail.getBoundingClientRect();
    const anchor = window.innerHeight * 0.45;
    const pct = clamp((anchor - box.top) / Math.max(box.height, 1), 0, 1);
    fill.style.transform = `scaleY(${pct})`;

    rows.forEach((row) => {
      const r = row.getBoundingClientRect();
      const on = r.top < anchor && r.bottom > anchor - r.height * 0.5;
      row.classList.toggle('is-active', on);
    });
  };

  window.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', sync, { passive: true });
  sync();
}

/* ------------------------------------------------------------ back to top */

function initToTop() {
  const btn = $('.totop');
  if (!btn) return;
  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
  });
}

/* ------------------------------------------------------------- accordions */

/* Native <details> already works; this just keeps only one open at a time. */
function initAccordions() {
  const groups = $$('[data-accordion]');
  groups.forEach((group) => {
    $$('details', group).forEach((d) => {
      d.addEventListener('toggle', () => {
        if (!d.open) return;
        $$('details', group).forEach((o) => o !== d && (o.open = false));
      });
    });
  });
}

/* ------------------------------------------------------------------- boot */

function boot() {
  initHeader();
  initDrawer();
  initReveal();
  initCounters();
  initMarquee();
  initScramble();
  initRail();
  initToTop();
  initAccordions();
  initCanvas();
  initCursor();
  initMagnetic();
  initTilt();

  /* WhatsApp widget slides in once the page has settled */
  const wa = $('.wa-float');
  if (wa) setTimeout(() => wa.classList.add('is-ready'), 700);

  document.documentElement.classList.add('is-booted');
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}