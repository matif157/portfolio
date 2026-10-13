/**
 * gravityh CRM — Frontend Runtime
 * Theme/mode resolution, sidebar state, toasts, command palette hook,
 * fetch helpers. No dependencies; loaded on every authenticated page.
 *
 * Server-rendered defaults come from header.php as window.GRAVITY_PREFS
 * (theme, mode, density, sidebar) so there is no flash of wrong theme.
 */
(function () {
  'use strict';

  var PREFS = window.GRAVITY_PREFS || {};
  var LS_KEY = 'gravityh.prefs';

  /* ---------- preference store (localStorage mirrors server defaults) ---------- */
  function readPrefs() {
    try { return Object.assign({}, PREFS, JSON.parse(localStorage.getItem(LS_KEY) || '{}')); }
    catch (e) { return Object.assign({}, PREFS); }
  }
  function writePrefs(patch) {
    var p = readPrefs();
    Object.keys(patch).forEach(function (k) { p[k] = patch[k]; });
    try { localStorage.setItem(LS_KEY, JSON.stringify(p)); } catch (e) { /* private mode */ }
    applyPrefs(p);
  }

  /* ---------- color mode: light | dark | auto ---------- */
  var mql = window.matchMedia('(prefers-color-scheme: dark)');
  function resolvedMode(prefs) {
    if (prefs.mode === 'dark') return 'dark';
    if (prefs.mode === 'light') return 'light';
    return mql.matches ? 'dark' : 'light'; // auto
  }
  function applyPrefs(p) {
    var html = document.documentElement;
    html.setAttribute('data-theme', p.theme || 'professional');
    html.setAttribute('data-mode', resolvedMode(p));
    html.setAttribute('data-density', p.density || 'standard');
    if (p.reducedMotion) html.setAttribute('data-motion', 'reduced');
    else html.removeAttribute('data-motion');
    // Sidebar state: collapsed only applies on desktop (CSS guards mobile)
    html.setAttribute('data-sidebar', p.sidebarCollapsed ? 'collapsed' : 'expanded');
  }
  // Keep "auto" in sync with OS changes without reload
  if (mql.addEventListener) {
    mql.addEventListener('change', function () { applyPrefs(readPrefs()); });
  }

  /* ---------- public API ---------- */
  window.GravityUI = {
    prefs: readPrefs,
    setTheme: function (t) { writePrefs({ theme: t }); },
    setMode: function (m) { writePrefs({ mode: m }); },       // light | dark | auto
    setDensity: function (d) { writePrefs({ density: d }); }, // compact | standard | comfortable
    toggleSidebar: function () {
      var p = readPrefs();
      if (window.innerWidth < 992) {
        // mobile: drawer open/close
        var open = document.documentElement.getAttribute('data-sidebar') === 'mobile-open';
        document.documentElement.setAttribute('data-sidebar', open ? 'collapsed' : 'mobile-open');
      } else {
        writePrefs({ sidebarCollapsed: !p.sidebarCollapsed });
      }
    },
    toast: toast
  };

  /* ---------- toasts ---------- */
  function ensureToastStack() {
    var stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('role', 'status');
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    return stack;
  }
  function toast(message, type, timeout) {
    type = type || 'success';
    var icons = { success: 'bi-check-circle', error: 'bi-exclamation-triangle', warning: 'bi-exclamation-circle', info: 'bi-info-circle' };
    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i><div>' + message + '</div>';
    ensureToastStack().appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.25s ease';
      setTimeout(function () { el.remove(); }, 260);
    }, timeout || 3500);
  }
/* ---------- command palette (Ctrl+K) ----------
     Focuses the existing global search in the top header and opens its
     results; a full standalone palette page ships with Theme Studio. */
  function initCommandPalette() {
    var search = document.querySelector('.global-search .search-input');
    document.addEventListener('keydown', function (ev) {
      if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
        ev.preventDefault();
        if (search) { search.focus(); search.select(); }
      }
      if (ev.key === 'Escape') {
        document.querySelectorAll('.dropdown-menu-custom.show').forEach(function (d) { d.classList.remove('show'); });
      }
    });
  }

  /* ---------- sidebar helpers ---------- */
  function initSidebar() {
    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (toggle) toggle.addEventListener('click', function () { window.GravityUI.toggleSidebar(); });
    var backdrop = document.querySelector('.sidebar-backdrop');
    if (backdrop) backdrop.addEventListener('click', function () {
      document.documentElement.setAttribute('data-sidebar', 'collapsed');
    });
  }

  /* ---------- theme mode quick switch (header button) ---------- */
  function initModeToggle() {
    var btn = document.querySelector('[data-mode-toggle]');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var p = readPrefs();
      var next = resolvedMode(p) === 'dark' ? 'light' : 'dark';
      writePrefs({ mode: next });
      toast(next === 'dark' ? 'Dark mode enabled' : 'Light mode enabled', 'info', 1800);
    });
  }
  /* ---------- fetch helper (JSON, same-origin, CSRF header) ---------- */
  function gravityFetch(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': (window.GRAVITY_PREFS && window.GRAVITY_PREFS.csrf) || ''
    }, options.headers || {});
    return fetch(url, options).then(function (res) {
      if (!res.ok) {
        // Human-readable failure, never a bare "Error 500"
        return res.json().catch(function () { return {}; }).then(function (body) {
          var msg = (body && (body.message || body.error)) ||
            'Request failed (HTTP ' + res.status + '). Please try again or reload the page.';
          throw new Error(msg);
        });
      }
      return res.json();
    });
  }
  window.GravityFetch = gravityFetch;

  /* ---------- boot ---------- */
  function boot() {
    applyPrefs(readPrefs());
    initCommandPalette();
    initSidebar();
    initModeToggle();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
