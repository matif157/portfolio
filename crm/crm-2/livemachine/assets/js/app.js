/* ===================================================================
   Global UI helpers — toasts, modals, fetch wrapper, device-status pill
   =================================================================== */
(() => {
    'use strict';

    // ---------- Toast ----------
    const toastContainer = (() => {
        let el = document.getElementById('toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast-container';
            document.body.appendChild(el);
        }
        return el;
    })();

    window.toast = (msg, kind = 'info', timeout = 4000) => {
        const t = document.createElement('div');
        t.className = `toast ${kind}`;
        const icon = { success: 'check-circle', error: 'alert-circle', warn: 'alert-triangle', info: 'info' }[kind] || 'info';
        t.innerHTML = `<i data-lucide="${icon}" style="width:18px;height:18px"></i><span class="msg"></span>`;
        t.querySelector('.msg').textContent = msg;
        toastContainer.appendChild(t);
        if (window.lucide) lucide.createIcons({ nodes: [t] });
        setTimeout(() => {
            t.classList.add('closing');
            setTimeout(() => t.remove(), 250);
        }, timeout);
    };

    // ---------- Spinner button helper ----------
    window.withLoading = async (btn, work) => {
        if (!btn) return work();
        const original = btn.innerHTML;
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Working…';
        try { return await work(); }
        finally {
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.innerHTML = original;
            if (window.lucide) lucide.createIcons({ nodes: [btn] });
        }
    };

    // ---------- CSRF + base URL ----------
    const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
    const base = document.querySelector('meta[name="base-url"]')?.content || '';
    window.CSRF = csrf;
    window.BASE = base;

    // ---------- Fetch wrapper ----------
    window.api = async (path, options = {}) => {
        const opts = { credentials: 'same-origin', headers: {}, ...options };
        if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            opts.headers['Content-Type'] = 'application/json';
            opts.headers['X-CSRF-Token'] = csrf;
            opts.body = JSON.stringify(opts.body);
        }
        const res = await fetch(path, opts);
        const ct = res.headers.get('Content-Type') || '';
        const data = ct.includes('json') ? await res.json() : await res.text();
        if (!res.ok || (data && data.ok === false)) {
            const err = new Error((data && data.error) || `HTTP ${res.status}`);
            err.data = data; err.status = res.status;
            throw err;
        }
        return data;
    };

    // ---------- Modal helper ----------
    window.confirmModal = ({ title = 'Confirm', message = '', confirmText = 'Confirm', danger = false } = {}) =>
        new Promise(resolve => {
            const html = `
                <div class="modal-backdrop open">
                    <div class="modal" role="dialog" aria-modal="true">
                        <header><h3></h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
                        <div class="body"></div>
                        <footer>
                            <button class="btn" data-cancel>Cancel</button>
                            <button class="btn ${danger ? 'danger' : 'primary'}" data-ok></button>
                        </footer>
                    </div>
                </div>`;
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            const root = tmp.firstElementChild;
            root.querySelector('h3').textContent = title;
            root.querySelector('.body').textContent = message;
            root.querySelector('[data-ok]').textContent = confirmText;
            document.body.appendChild(root);
            if (window.lucide) lucide.createIcons({ nodes: [root] });
            const close = (val) => { root.classList.remove('open'); setTimeout(() => root.remove(), 150); resolve(val); };
            root.querySelector('[data-ok]').onclick    = () => close(true);
            root.querySelector('[data-cancel]').onclick = () => close(false);
            root.querySelector('[data-x]').onclick      = () => close(false);
            root.addEventListener('click', e => { if (e.target === root) close(false); });
        });

    // ---------- Device status pill ----------
    const pill = document.getElementById('device-pill');
    async function updateStatus() {
        if (!pill) return;
        try {
            const r = await api(`${base}/api/device_status.php`);
            pill.classList.toggle('online',  !!r.online);
            pill.classList.toggle('offline', !r.online);
            pill.querySelector('.label').textContent = r.online ? 'Device online' : 'Device offline';
        } catch { pill.classList.remove('online'); pill.classList.add('offline'); }
    }
    if (pill) { updateStatus(); setInterval(updateStatus, 10000); }

    // ---------- Live device clock (reads the biometric machine's own clock) ----------
    // Fetches the device time once a minute (CMD_GET_TIME) and ticks it locally
    // every second in between, so the header shows the machine's live time —
    // not the PC clock.
    const clockEl = document.getElementById('device-clock');
    if (clockEl) {
        const timeSpan  = clockEl.querySelector('.clock-time');
        let deviceEpoch = null;   // device wall-clock at last sync (ms)
        let syncedAt    = null;   // Date.now() baseline at last sync

        const render = () => {
            if (deviceEpoch === null) return;
            const t   = new Date(deviceEpoch + (Date.now() - syncedAt));
            const h24 = t.getHours();
            const hh  = String(h24 % 12 || 12).padStart(2, '0');
            const mm  = String(t.getMinutes()).padStart(2, '0');
            const ss  = String(t.getSeconds()).padStart(2, '0');
            const ap  = h24 < 12 ? 'AM' : 'PM';
            timeSpan.textContent = `${hh}:${mm}:${ss} ${ap}`;
        };

        const syncClock = async () => {
            try {
                const r = await api(`${base}/api/device_time.php`);
                const parsed = r.device_time ? new Date(String(r.device_time).replace(' ', 'T')) : null;
                if (parsed && !isNaN(parsed)) {
                    deviceEpoch = parsed.getTime();
                    syncedAt    = Date.now();
                    clockEl.hidden = false;
                    clockEl.classList.remove('stale');
                    render();
                }
            } catch {
                clockEl.classList.add('stale'); // dim last-known value when device unreachable
            }
        };

        syncClock();
        setInterval(render, 1000);      // tick locally every second
        setInterval(syncClock, 60000);  // re-sync with the device every 60s
    }

    // ---------- Theme toggle (light / dark) ----------
    const themeBtn = document.getElementById('theme-toggle');
    const applyThemeIcon = () => {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (themeBtn) themeBtn.innerHTML = `<i data-lucide="${dark ? 'moon' : 'sun'}"></i>`;
        if (window.lucide) lucide.createIcons({ nodes: themeBtn ? [themeBtn] : undefined });
    };
    applyThemeIcon();
    themeBtn?.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('lv-theme', next); } catch (e) {}
        applyThemeIcon();
    });

    // ---------- DataTables auto-init (opt-in via data-dt) ----------
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery(function ($) {
            $('table[data-dt]').each(function () {
                if ($.fn.DataTable.isDataTable(this)) return;
                const $t = $(this);
                const title = $t.data('dt-title') || 'export';
                const order = $t.data('dt-order');
                const noOrder = $t.data('dt-noorder'); // e.g. [7] — columns to leave unsortable
                $t.DataTable({
                    // accepts [0,"asc"] or a multi-column [[2,"desc"],[3,"desc"]]
                    order: Array.isArray(order) ? (Array.isArray(order[0]) ? order : [order]) : [],
                    columnDefs: Array.isArray(noOrder) ? [{ targets: noOrder, orderable: false, searchable: false }] : [],
                    pageLength: parseInt($t.data('dt-page'), 10) || 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    layout: {
                        topStart: {
                            buttons: [
                                { extend: 'copyHtml5',  title, text: '<i data-lucide="clipboard"></i> Copy' },
                                { extend: 'csvHtml5',   title, text: '<i data-lucide="file-down"></i> CSV' },
                                { extend: 'excelHtml5', title, text: '<i data-lucide="sheet"></i> Excel' },
                                { extend: 'print',      title, text: '<i data-lucide="printer"></i> Print' }
                            ]
                        },
                        topEnd: 'search',
                        bottomStart: ['info', 'pageLength'],
                        bottomEnd: 'paging'
                    }
                });
            });
            if (window.lucide) lucide.createIcons();
        });
    }

    // Activate lucide everywhere on first paint
    if (window.lucide) lucide.createIcons();

})();
