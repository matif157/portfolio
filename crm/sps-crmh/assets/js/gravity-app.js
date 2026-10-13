/**
 * ══════════════════════════════════════════════════════════════════════════
 *  GRAVITY CRM — Global Application Script v2.0
 *  Sidebar Manager | Theme Engine | Command Palette | Popover System
 * ══════════════════════════════════════════════════════════════════════════
 */

(function () {
    'use strict';

    /* ─── Constants ─────────────────────────────────────────────────── */
    const STORAGE_PREFIX   = 'gravity_crm_';
    const LS_THEME         = STORAGE_PREFIX + 'theme';
    const LS_MODE          = STORAGE_PREFIX + 'mode';
    const LS_SIDEBAR       = STORAGE_PREFIX + 'sidebar_collapsed';
    const LS_NAV_OPEN      = STORAGE_PREFIX + 'nav_open_sections';

    /* ─── Utility ────────────────────────────────────────────────────── */
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }
    function ls(key, val) {
        if (val === undefined) { try { return localStorage.getItem(key); } catch { return null; } }
        try { localStorage.setItem(key, val); } catch { /* quota exceeded, ignore */ }
    }

    /* ══════════════════════════════════════════════════════════════════
       THEME ENGINE
       ══════════════════════════════════════════════════════════════════ */
    const ThemeEngine = {
        themes: ['professional', 'aurora', 'executive', 'studio', 'focus'],
        modes:  ['light', 'dark', 'auto'],

        init() {
            const savedTheme = ls(LS_THEME) || 'professional';
            const savedMode  = ls(LS_MODE)  || 'auto';
            this.apply(savedTheme, savedMode);
            this._bindSwatches();
            this._bindModeToggle();
            // Listen for system mode change
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                const mode = ls(LS_MODE) || 'auto';
                if (mode === 'auto') this.apply(ls(LS_THEME) || 'professional', 'auto');
            });
        },

        apply(theme, mode) {
            const root = document.documentElement;
            root.setAttribute('data-theme', theme);
            root.setAttribute('data-mode', mode);
            ls(LS_THEME, theme);
            ls(LS_MODE, mode);

            // Update swatch UI
            $$('.theme-swatch').forEach(sw => {
                sw.classList.toggle('active', sw.dataset.theme === theme);
            });

            // Update mode toggle
            const toggle = $('#darkModeToggle');
            if (toggle) toggle.checked = (mode === 'dark');

            // Persist to server (fire and forget)
            this._persist(theme, mode);
        },

        _persist(theme, mode) {
            if (!window.GRAVITY_USER_ID) return;
            const url = (window.GRAVITY_BASE_URL || '') + 'ajax/set_theme.php';
            const fd = new FormData();
            fd.append('theme', theme);
            fd.append('mode', mode);
            fd.append('csrf_token', window.GRAVITY_CSRF || '');
            fetch(url, { method: 'POST', body: fd })
              .catch(() => { /* network error, ignore — localStorage already updated */ });
        },

        _bindSwatches() {
            document.addEventListener('click', (e) => {
                const sw = e.target.closest('.theme-swatch');
                if (!sw) return;
                const theme = sw.dataset.theme;
                const mode  = ls(LS_MODE) || 'auto';
                if (theme) this.apply(theme, mode);
            });
        },

        _bindModeToggle() {
            document.addEventListener('change', (e) => {
                if (e.target.id === 'darkModeToggle') {
                    const mode = e.target.checked ? 'dark' : 'light';
                    const theme = ls(LS_THEME) || 'professional';
                    this.apply(theme, mode);
                }
            });
        }
    };

    /* ══════════════════════════════════════════════════════════════════
       SIDEBAR MANAGER
       ══════════════════════════════════════════════════════════════════ */
    const SidebarManager = {
        shell: null,
        sidebar: null,
        collapsed: false,

        init() {
            this.shell   = $('.crm-shell');
            this.sidebar = $('.crm-sidebar');
            if (!this.shell) return;

            // Restore collapsed state
            const savedCollapsed = ls(LS_SIDEBAR);
            if (savedCollapsed === 'true') {
                this.shell.classList.add('sidebar-collapsed');
                this.collapsed = true;
            }

            // Restore open nav sections
            this._restoreNavState();

            // Bind toggle button
            const toggleBtn = $('#sidebarToggleBtn');
            if (toggleBtn) toggleBtn.addEventListener('click', () => this.toggle());

            // Bind mobile hamburger (topbar)
            const hamburger = $('#mobileMenuBtn');
            if (hamburger) hamburger.addEventListener('click', () => this._toggleMobile());

            // Bind nav group expand/collapse
            $$('.nav-group-toggle').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    const parent = el.closest('.nav-item');
                    if (parent) this._toggleNavGroup(parent);
                });
            });

            // Close mobile sidebar on outside click
            document.addEventListener('click', (e) => {
                if (!this.sidebar) return;
                if (this.sidebar.classList.contains('mobile-open') &&
                    !this.sidebar.contains(e.target) &&
                    !e.target.closest('#mobileMenuBtn')) {
                    this.sidebar.classList.remove('mobile-open');
                }
            });
        },

        toggle() {
            this.collapsed = !this.collapsed;
            this.shell.classList.toggle('sidebar-collapsed', this.collapsed);
            ls(LS_SIDEBAR, this.collapsed.toString());
        },

        _toggleMobile() {
            this.sidebar.classList.toggle('mobile-open');
        },

        _toggleNavGroup(parent) {
            parent.classList.toggle('open');
            this._saveNavState();
        },

        _saveNavState() {
            const open = $$('.nav-item.open').map(el => el.dataset.navId).filter(Boolean);
            ls(LS_NAV_OPEN, JSON.stringify(open));
        },

        _restoreNavState() {
            try {
                const raw = ls(LS_NAV_OPEN);
                if (!raw) return;
                const ids = JSON.parse(raw);
                ids.forEach(id => {
                    const el = $(`[data-nav-id="${id}"]`);
                    if (el) el.classList.add('open');
                });
            } catch { /* ignore */ }
        }
    };

    /* ══════════════════════════════════════════════════════════════════
       COMMAND PALETTE (Ctrl+K)
       ══════════════════════════════════════════════════════════════════ */
    const CommandPalette = {
        overlay: null,
        input: null,
        results: null,
        focusedIndex: -1,
        allItems: [],

        commands: [
            { icon: '🏠', label: 'Dashboard',           href: 'dashboard.php',            section: 'Navigation' },
            { icon: '👥', label: 'Clients',              href: 'clients.php',              section: 'Navigation' },
            { icon: '➕', label: 'Add New Client',       href: 'add_client.php',           section: 'Quick Actions' },
            { icon: '🌟', label: 'Leads & Acquisition',  href: 'leads.php',                section: 'Navigation' },
            { icon: '🏢', label: 'Businesses',           href: 'businesses.php',           section: 'Navigation' },
            { icon: '📋', label: 'Projects',             href: 'projects.php',             section: 'Navigation' },
            { icon: '✅', label: 'Tasks',                href: 'tasks.php',                section: 'Navigation' },
            { icon: '📅', label: 'Calendar',             href: 'calendar.php',             section: 'Navigation' },
            { icon: '🔔', label: 'Reminders',            href: 'reminders.php',            section: 'Navigation' },
            { icon: '⚙️', label: 'Services',             href: 'services.php',             section: 'Navigation' },
            { icon: '📄', label: 'Invoices',             href: 'invoices.php',             section: 'Finance' },
            { icon: '💳', label: 'Payments',             href: 'payments.php',             section: 'Finance' },
            { icon: '🧾', label: 'Quotations',           href: 'quotations.php',           section: 'Finance' },
            { icon: '🏦', label: 'Bank Statements',      href: 'bank_statements.php',      section: 'Finance' },
            { icon: '📁', label: 'Documents',            href: 'documents.php',            section: 'Docs' },
            { icon: '🔒', label: 'Secure Vault',         href: 'secure_area.php',          section: 'Docs' },
            { icon: '💬', label: 'Communications',       href: 'communications.php',       section: 'Comms' },
            { icon: '📧', label: 'Send Email',           href: 'email.php',                section: 'Comms' },
            { icon: '👤', label: 'My Profile',           href: 'profile.php',              section: 'Account' },
            { icon: '📝', label: 'Personal Templates',   href: 'personal_templates.php',   section: 'Account' },
            { icon: '📜', label: 'Activity Logs',        href: 'activity_logs.php',        section: 'Account' },
            { icon: '👥', label: 'Users & Staff',        href: 'users.php',                section: 'Admin' },
            { icon: '🔑', label: 'Roles & Permissions',  href: 'roles.php',                section: 'Admin' },
            { icon: '🎛️', label: 'CRM Customizer',       href: 'customizer.php',           section: 'Admin' },
            { icon: '⚡', label: 'Workflow Triggers',    href: 'workflow_triggers.php',    section: 'Admin' },
            { icon: '🔧', label: 'System Settings',      href: 'settings.php',             section: 'Admin' },
            { icon: '🐞', label: 'Error Logs',           href: 'error_logs.php',           section: 'Admin' },
            { icon: '🎙️', label: 'AI Voice Assistant',   action: 'openVoiceModal',         section: 'AI Tools' },
            { icon: '⚡', label: 'AI Quick Add',         action: 'openQuickAddModal',      section: 'AI Tools' },
            { icon: '🌙', label: 'Toggle Dark Mode',     action: 'toggleDarkMode',         section: 'Preferences' },
            { icon: '🚪', label: 'Logout',               href: 'logout.php',               section: 'Account' },
        ],

        init() {
            this.overlay = $('#cmdPaletteOverlay');
            if (!this.overlay) return;

            this.input   = this.overlay.querySelector('.cmd-input');
            this.results = this.overlay.querySelector('.cmd-results');

            // Keyboard shortcut
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.open();
                }
                if (e.key === 'Escape') this.close();
                if (this.overlay.classList.contains('active')) {
                    if (e.key === 'ArrowDown') { e.preventDefault(); this._moveFocus(1); }
                    if (e.key === 'ArrowUp')   { e.preventDefault(); this._moveFocus(-1); }
                    if (e.key === 'Enter')     { e.preventDefault(); this._selectFocused(); }
                }
            });

            // Search input
            if (this.input) {
                this.input.addEventListener('input', () => this.render(this.input.value));
            }

            // Close on overlay click
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) this.close();
            });

            // Global topbar search button opens palette
            const searchTrigger = $('#globalSearchTrigger');
            if (searchTrigger) searchTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                this.open();
            });

            this.render('');
        },

        open() {
            if (!this.overlay) return;
            this.overlay.classList.add('active');
            if (this.input) {
                this.input.value = '';
                this.input.focus();
            }
            this.render('');
            this.focusedIndex = -1;
        },

        close() {
            if (!this.overlay) return;
            this.overlay.classList.remove('active');
            this.focusedIndex = -1;
        },

        render(query) {
            if (!this.results) return;
            query = (query || '').toLowerCase().trim();

            const filtered = query
                ? this.commands.filter(c => c.label.toLowerCase().includes(query) || c.section.toLowerCase().includes(query))
                : this.commands;

            const sections = {};
            filtered.forEach(cmd => {
                if (!sections[cmd.section]) sections[cmd.section] = [];
                sections[cmd.section].push(cmd);
            });

            let html = '';
            this.allItems = [];
            let itemIndex = 0;

            Object.entries(sections).forEach(([section, items]) => {
                html += `<div class="cmd-section-label">${section}</div>`;
                items.forEach(cmd => {
                    this.allItems.push(cmd);
                    const href = cmd.href || '#';
                    html += `<a class="cmd-item" href="${href}" data-index="${itemIndex++}"
                        ${cmd.action ? `data-action="${cmd.action}"` : ''}>
                        <span class="cmd-item-icon">${cmd.icon}</span>
                        <span class="cmd-item-label">${cmd.label}</span>
                        <span class="cmd-item-meta">${section}</span>
                    </a>`;
                });
            });

            if (!html) {
                html = '<div class="popover-empty">No results for "' + (query || '') + '"</div>';
            }

            this.results.innerHTML = html;
            this.focusedIndex = -1;

            // Bind item clicks
            $$('.cmd-item', this.results).forEach(item => {
                item.addEventListener('click', (e) => {
                    const action = item.dataset.action;
                    if (action) {
                        e.preventDefault();
                        this.close();
                        this._runAction(action);
                    } else {
                        this.close();
                    }
                });
            });
        },

        _moveFocus(direction) {
            const items = $$('.cmd-item', this.results);
            if (!items.length) return;
            items.forEach(i => i.classList.remove('focused'));
            this.focusedIndex = Math.max(0, Math.min(items.length - 1, this.focusedIndex + direction));
            const focused = items[this.focusedIndex];
            if (focused) {
                focused.classList.add('focused');
                focused.scrollIntoView({ block: 'nearest' });
            }
        },

        _selectFocused() {
            const items = $$('.cmd-item', this.results);
            const focused = items[this.focusedIndex];
            if (focused) focused.click();
        },

        _runAction(action) {
            if (action === 'openVoiceModal'   && typeof openVoiceModal === 'function')    openVoiceModal();
            if (action === 'openQuickAddModal' && typeof openQuickAddModal === 'function') openQuickAddModal();
            if (action === 'toggleDarkMode')   ThemeEngine.apply(ls(LS_THEME) || 'professional', ls(LS_MODE) === 'dark' ? 'light' : 'dark');
        }
    };

    /* ══════════════════════════════════════════════════════════════════
       POPOVER SYSTEM
       ══════════════════════════════════════════════════════════════════ */
    const PopoverSystem = {
        active: null,

        init() {
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-popover]');
                const isInside = e.target.closest('.popover, .user-dropdown-menu');

                if (trigger) {
                    e.stopPropagation();
                    const targetId = trigger.dataset.popover;
                    this.toggle(targetId);
                    return;
                }

                if (!isInside) this.closeAll();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeAll();
            });
        },

        toggle(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const isOpen = el.classList.contains('active');
            this.closeAll();
            if (!isOpen) {
                el.classList.add('active');
                this.active = el;
            }
        },

        closeAll() {
            $$('.popover.active, .user-dropdown-menu.active').forEach(el => el.classList.remove('active'));
            this.active = null;
        }
    };

    /* ══════════════════════════════════════════════════════════════════
       MODAL HELPERS (Global)
       ══════════════════════════════════════════════════════════════════ */
    const ModalManager = {
        init() {
            // Close on overlay click
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal-overlay')) {
                    e.target.style.display = 'none';
                }
            });

            // Close on Escape (for any open modal)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    $$('.modal-overlay[style*="flex"]').forEach(m => m.style.display = 'none');
                }
            });
        }
    };

    /* ══════════════════════════════════════════════════════════════════
       FLASH MESSAGE AUTO-DISMISS
       ══════════════════════════════════════════════════════════════════ */
    function initFlashMessages() {
        $$('.alert[data-auto-dismiss]').forEach(alert => {
            const delay = parseInt(alert.dataset.autoDismiss, 10) || 5000;
            setTimeout(() => {
                alert.style.transition = 'opacity 0.4s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            }, delay);
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.alert-close');
            if (btn) btn.closest('.alert').remove();
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       GLOBAL MODAL FUNCTIONS (backward compat with existing pages)
       ══════════════════════════════════════════════════════════════════ */
    window.openVoiceModal = function () {
        const m = document.getElementById('aiVoiceModal');
        if (m) m.style.display = 'flex';
        // Initialize speech recognition when modal opens
        if (window.gravityAI) window.gravityAI.init();
    };

    window.closeVoiceModal = function () {
        const m = document.getElementById('aiVoiceModal');
        if (m) m.style.display = 'none';
        if (window.gravityAI && window.gravityAI.recognition) {
            try { window.gravityAI.recognition.stop(); } catch(e) {}
        }
    };

    window.openQuickAddModal = function () {
        const m = document.getElementById('quickAddModal');
        if (m) {
            m.style.display = 'flex';
            const inp = document.getElementById('quickAddInput');
            if (inp) { inp.value = ''; inp.focus(); }
            const res = document.getElementById('quickAddResult');
            if (res) res.style.display = 'none';
        }
    };

    window.closeQuickAddModal = function () {
        const m = document.getElementById('quickAddModal');
        if (m) m.style.display = 'none';
    };

    /* ─── Gravity AI Engine ──────────────────────────────────────────── */
    window.gravityAI = {
        recognition: null,
        baseUrl: (window.GRAVITY_BASE_URL || ''),

        init() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const statusEl = document.getElementById('speechStatus');
            const micBtn   = document.getElementById('micBtn');

            if (!SpeechRecognition) {
                if (statusEl) statusEl.innerText = '⚠️ Voice not supported in this browser. Use the text input.';
                if (micBtn) micBtn.style.opacity = '0.4';
                return;
            }

            if (!this.recognition) {
                this.recognition = new SpeechRecognition();
                this.recognition.lang = 'en-US';
                this.recognition.interimResults = false;

                this.recognition.onstart = () => {
                    if (micBtn) { micBtn.style.transform = 'scale(1.2)'; micBtn.style.boxShadow = '0 0 40px rgba(239,68,68,0.7)'; micBtn.textContent = '🔴'; }
                    if (statusEl) statusEl.innerText = '🎤 Listening… speak your command now';
                };

                this.recognition.onspeechend = () => {
                    this.recognition.stop();
                    if (micBtn) { micBtn.style.transform = 'scale(1.0)'; micBtn.style.boxShadow = '0 0 28px rgba(79,70,229,0.45)'; micBtn.textContent = '🎙️'; }
                    if (statusEl) statusEl.innerText = '⏳ Processing…';
                };

                this.recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript;
                    if (statusEl) statusEl.innerText = `✅ Heard: "${transcript}"`;
                    this.sendToBackend(transcript, 'voice');
                };

                this.recognition.onerror = (err) => {
                    if (micBtn) { micBtn.style.transform = 'scale(1.0)'; micBtn.style.textContent = '🎙️'; }
                    if (statusEl) statusEl.innerText = `❌ Error: ${err.error || 'unknown'}. Try again or use text input.`;
                };
            }
        },

        startListening() {
            this.init();
            if (this.recognition) {
                try { this.recognition.start(); }
                catch(e) {
                    const s = document.getElementById('speechStatus');
                    if (s) s.innerText = '⚠️ Already listening or recognition unavailable.';
                }
            }
        },

        sendText(text) {
            text = (text || '').trim();
            if (!text) return;
            const statusEl = document.getElementById('speechStatus');
            if (statusEl) statusEl.innerText = `⏳ Processing: "${text}"`;
            this.sendToBackend(text, 'text');
        },

        async sendToBackend(prompt, source) {
            const outputEl = document.getElementById('speechText');
            const statusEl = document.getElementById('speechStatus');

            if (outputEl) outputEl.innerHTML = '<em style="opacity:0.6;">Thinking…</em>';

            try {
                const resp = await fetch(this.baseUrl + 'AIPromptController.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt })
                });

                if (!resp.ok) throw new Error('HTTP ' + resp.status);

                const data = await resp.json();

                if (data.success) {
                    const responseHtml = `<strong>Action:</strong> ${data.action_executed}<br><br>${data.response_text}`;
                    if (outputEl) outputEl.innerHTML = responseHtml;
                    if (statusEl) statusEl.innerText = source === 'voice' ? '✅ Done. Response spoken.' : '✅ AI responded.';
                    this._speak(data.speech_text || data.response_text);
                } else {
                    if (outputEl) outputEl.innerText = '⚠️ AI Error: ' + (data.error || 'Unknown error.');
                    if (statusEl) statusEl.innerText = '❌ AI could not process your request.';
                }
            } catch (err) {
                if (outputEl) outputEl.innerText = '❌ Connection error: ' + err.message;
                if (statusEl) statusEl.innerText = '❌ Failed to reach AI backend.';
                console.error('[GravityAI]', err);
            }
        },

        _speak(text) {
            if ('speechSynthesis' in window && text) {
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 1.05;
                utterance.pitch = 1.0;
                window.speechSynthesis.speak(utterance);
            }
        }
    };

    window.executeQuickAdd = function () {
        const input  = document.getElementById('quickAddInput');
        const resEl  = document.getElementById('quickAddResult');
        const btn    = document.getElementById('quickAddBtn');
        const text   = (input ? input.value : '').trim();

        if (!text) { if (input) { input.focus(); } return; }

        if (btn)   { btn.disabled = true; btn.textContent = 'Processing…'; }
        if (resEl) { resEl.style.display = 'block'; resEl.innerHTML = '<em style="opacity:0.6;">Thinking…</em>'; }

        const baseUrl = window.GRAVITY_BASE_URL || '';
        fetch(baseUrl + 'AIPromptController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: text })
        })
        .then(r => r.json())
        .then(data => {
            if (btn) { btn.disabled = false; btn.textContent = 'Process with AI'; }
            if (data.success) {
                if (resEl) resEl.innerHTML = `<strong>Action:</strong> ${data.action_executed}<br><br>${data.response_text}`;
                window.gravityAI._speak(data.speech_text || data.response_text);
            } else {
                if (resEl) resEl.innerText = '⚠️ ' + (data.error || 'AI could not process the request.');
            }
        })
        .catch(err => {
            if (btn) { btn.disabled = false; btn.textContent = 'Process with AI'; }
            if (resEl) resEl.innerText = '❌ Connection error: ' + err.message;
            console.error('[GravityAI QuickAdd]', err);
        });
    };

    /* Legacy popover toggle — kept for pages still using inline onclick */
    window.togglePopover = function (id) {
        PopoverSystem.toggle(id);
    };

    /* ══════════════════════════════════════════════════════════════════
       ACTIVE NAV HIGHLIGHT
       ══════════════════════════════════════════════════════════════════ */
    function highlightActiveNav() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        $$('.nav-link[href]').forEach(link => {
            const href = link.getAttribute('href').split('?')[0];
            if (href === currentPage) {
                link.classList.add('active');
                const parent = link.closest('.nav-item');
                if (parent) {
                    parent.classList.add('active');
                    const parentItem = parent.parentElement?.closest('.nav-item');
                    if (parentItem) { parentItem.classList.add('open', 'active'); }
                }
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       INIT
       ══════════════════════════════════════════════════════════════════ */
    function init() {
        ThemeEngine.init();
        SidebarManager.init();
        CommandPalette.init();
        PopoverSystem.init();
        ModalManager.init();
        initFlashMessages();
        highlightActiveNav();

        // Expose for use in theme switcher UI
        window.GravityTheme = ThemeEngine;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
