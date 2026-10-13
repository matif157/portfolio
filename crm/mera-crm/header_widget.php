<?php
/**
 * =====================================================
 * FILE: header_widget.php
 * PURPOSE: Common top header for all CRM pages
 * Include: require_once 'header_widget.php';
 * Place: main content area ke top pe (sidebar ke baad)
 * =====================================================
 */

if (!isset($_SESSION['user_id'])) {
    return;
}

$h_name  = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$h_role  = htmlspecialchars($_SESSION['role_name'] ?? 'Staff');
$h_email = htmlspecialchars($currentUser['email'] ?? ($_SESSION['email'] ?? ''));
$h_initial = strtoupper(substr($h_name, 0, 1));
?>

<style>
/* ========== HEADER STYLES ========== */
.crm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 18px;
    margin-bottom: 22px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 16px;
    backdrop-filter: blur(14px);
    position: relative;
    z-index: 50;
}

.crm-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
}

.crm-header-title {
    color: #fff;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.crm-header-sub {
    color: #94a3b8;
    font-size: 12px;
    margin: 0;
}

.crm-header-center {
    flex: 1;
    max-width: 520px;
    position: relative;
}

.crm-search-input {
    width: 100%;
    background: rgba(15,23,42,0.7);
    border: 1px solid rgba(255,255,255,0.12);
    color: #e2e8f0;
    border-radius: 12px;
    padding: 11px 14px 11px 40px;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
}

.crm-search-input:focus {
    border-color: #38bdf8;
}

.crm-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
}

.crm-search-results {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    max-height: 320px;
    overflow-y: auto;
    display: none;
    z-index: 100;
    box-shadow: 0 16px 40px rgba(0,0,0,0.45);
}

.crm-search-results.open { display: block; }

.crm-search-item {
    padding: 10px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    cursor: pointer;
    color: #e2e8f0;
    text-decoration: none;
    display: block;
}

.crm-search-item:hover { background: rgba(255,255,255,0.06); }
.crm-search-item small { color: #94a3b8; }

.crm-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.crm-icon-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.06);
    color: #e2e8f0;
    cursor: pointer;
    font-size: 16px;
}

.crm-icon-btn:hover { background: rgba(255,255,255,0.12); }

.crm-profile-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px 6px 6px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.06);
    color: #e2e8f0;
    cursor: pointer;
}

.crm-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.crm-profile-meta {
    text-align: left;
    line-height: 1.2;
}

.crm-profile-meta strong {
    display: block;
    font-size: 13px;
}

.crm-profile-meta span {
    font-size: 11px;
    color: #94a3b8;
}

/* Dropdown panels */
.crm-drop {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 340px;
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    box-shadow: 0 18px 40px rgba(0,0,0,0.45);
    display: none;
    z-index: 120;
    overflow: hidden;
}
.crm-drop.open { display: block; }

.crm-drop-head {
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    font-weight: 600;
    color: #fff;
}

.crm-drop-body {
    padding: 12px 14px;
    max-height: 360px;
    overflow-y: auto;
    color: #cbd5e1;
    font-size: 13px;
}

.crm-drop-item {
    padding: 10px;
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    margin-bottom: 8px;
}

.crm-drop-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 10px 0;
}

.crm-drop-actions button {
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.06);
    color: #e2e8f0;
    border-radius: 10px;
    padding: 9px;
    cursor: pointer;
}

@media (max-width: 900px) {
    .crm-header {
        flex-wrap: wrap;
    }
    .crm-header-center {
        order: 3;
        max-width: 100%;
        width: 100%;
    }
    .crm-profile-meta { display: none; }
}
</style>

<div class="crm-header" id="crmHeader">
    <div class="crm-header-left">
        <div>
            <p class="crm-header-title"><?= defined('SITE_NAME') ? SITE_NAME : 'SPS CRM' ?></p>
            <p class="crm-header-sub">Professional Services Workspace</p>
        </div>
    </div>

    <div class="crm-header-center">
        <span class="crm-search-icon">🔍</span>
        <input type="text" class="crm-search-input" id="headerSearchInput"
               placeholder="Search clients, invoices, users..."
               oninput="headerDoSearch()"
               autocomplete="off">
        <div class="crm-search-results" id="headerSearchResults"></div>
    </div>

    <div class="crm-header-right">
        <button class="crm-icon-btn" title="Activities" onclick="headerToggle('activitiesDrop')">📋</button>
        <button class="crm-icon-btn" title="AI Copilot" onclick="headerToggle('aiDrop')">🤖</button>
        <button class="crm-icon-btn" title="Voice (soon)" onclick="alert('Urdu Voice Assistant next step mein add hoga')">🎤</button>

        <button class="crm-profile-btn" onclick="headerToggle('profileDrop')">
            <div class="crm-avatar"><?= $h_initial ?></div>
            <div class="crm-profile-meta">
                <strong><?= $h_name ?></strong>
                <span><?= $h_role ?></span>
            </div>
        </button>

        <!-- Profile Dropdown -->
        <div class="crm-drop" id="profileDrop" style="right:0;">
            <div class="crm-drop-head">My Profile</div>
            <div class="crm-drop-body" id="profileDropBody">
                Loading...
            </div>
        </div>

        <!-- Activities Dropdown -->
        <div class="crm-drop" id="activitiesDrop" style="right:60px;">
            <div class="crm-drop-head">Recent Activities</div>
            <div class="crm-drop-body" id="activitiesDropBody">
                Loading...
            </div>
        </div>

        <!-- AI Dropdown -->
        <div class="crm-drop" id="aiDrop" style="right:110px; width:360px;">
            <div class="crm-drop-head">AI Copilot</div>
            <div class="crm-drop-body" id="aiDropBody" style="min-height:180px;">
                <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:10px; margin-bottom:10px;">
                    Assalam o Alaikum! CRM se related sawal pooch sakte ho.
                </div>
            </div>
            <div style="padding:10px 14px; border-top:1px solid rgba(255,255,255,0.08); display:flex; gap:8px;">
                <input type="text" id="headerAiInput" class="crm-search-input" style="padding-left:12px;" placeholder="Ask something...">
                <button class="crm-icon-btn" onclick="headerSendAI()">➤</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let searchTimer = null;

    window.headerToggle = function(id) {
        const el = document.getElementById(id);
        const wasOpen = el.classList.contains('open');
        document.querySelectorAll('.crm-drop').forEach(d => d.classList.remove('open'));
        if (!wasOpen) {
            el.classList.add('open');
            if (id === 'profileDrop') headerLoadProfile();
            if (id === 'activitiesDrop') headerLoadActivities();
        }
    };

    // outside click close
    document.addEventListener('click', function(e) {
        const header = document.getElementById('crmHeader');
        if (header && !header.contains(e.target)) {
            document.querySelectorAll('.crm-drop').forEach(d => d.classList.remove('open'));
            document.getElementById('headerSearchResults').classList.remove('open');
        }
    });

    window.headerDoSearch = function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async function() {
            const q = document.getElementById('headerSearchInput').value.trim();
            const box = document.getElementById('headerSearchResults');
            if (q.length < 2) {
                box.classList.remove('open');
                box.innerHTML = '';
                return;
            }
            box.innerHTML = '<div class="crm-search-item"><small>Searching...</small></div>';
            box.classList.add('open');

            try {
                const res = await fetch('footer_api.php?action=search&q=' + encodeURIComponent(q));
                const data = await res.json();
                if (!data.success || !data.results.length) {
                    box.innerHTML = '<div class="crm-search-item"><small>No results</small></div>';
                    return;
                }
                box.innerHTML = data.results.map(r => `
                    <a class="crm-search-item" href="${r.link}">
                        <strong>${esc(r.title)}</strong><br>
                        <small>${esc(r.type)} ${r.subtitle ? '• ' + esc(r.subtitle) : ''}</small>
                    </a>
                `).join('');
            } catch (e) {
                box.innerHTML = '<div class="crm-search-item"><small>Network error</small></div>';
            }
        }, 280);
    };

    window.headerLoadProfile = async function() {
        const box = document.getElementById('profileDropBody');
        box.innerHTML = 'Loading...';
        try {
            const res = await fetch('footer_api.php?action=profile');
            const data = await res.json();
            if (!data.success) {
                box.innerHTML = data.error || 'Error';
                return;
            }
            const u = data.user;
            box.innerHTML = `
                <div style="line-height:1.8; margin-bottom:8px;">
                    <div><b>Username:</b> ${esc(u.username)}</div>
                    <div><b>Email:</b> ${esc(u.email)}</div>
                    <div><b>Designation:</b> ${esc(u.designation || '-')}</div>
                    <div><b>Phone:</b> ${esc(u.phone || '-')}</div>
                    <div><b>Last Login:</b> ${esc(u.last_login || 'Never')}</div>
                </div>
                <div class="crm-drop-actions">
                    <button onclick="headerLoadActivities()">My Activities</button>
                    <button onclick="location.href='edit_user.php?id=<?= (int)$_SESSION['user_id'] ?>'">Edit Profile</button>
                </div>
                <div class="crm-drop-actions">
                    <div class="crm-drop-item" style="text-align:center; margin:0;"><b>${u.activity_count || 0}</b><br><small>Activities</small></div>
                    <div class="crm-drop-item" style="text-align:center; margin:0;"><b>${u.invoice_count || 0}</b><br><small>Invoices</small></div>
                </div>
                <button class="crm-drop-item" style="width:100%; border:none; color:#f87171; cursor:pointer;" onclick="location.href='logout.php'">Logout</button>
            `;
        } catch (e) {
            box.innerHTML = 'Network error';
        }
    };

    window.headerLoadActivities = async function() {
        // profile ke andar bhi call ho sakta hai, is liye activities panel open karein
        document.querySelectorAll('.crm-drop').forEach(d => d.classList.remove('open'));
        document.getElementById('activitiesDrop').classList.add('open');

        const box = document.getElementById('activitiesDropBody');
        box.innerHTML = 'Loading...';
        try {
            const res = await fetch('footer_api.php?action=my_activities');
            const data = await res.json();
            if (!data.success) {
                box.innerHTML = data.error || 'Error';
                return;
            }
            if (!data.logs.length) {
                box.innerHTML = 'No activities yet.';
                return;
            }
            box.innerHTML = data.logs.map(l => `
                <div class="crm-drop-item">
                    <b>${esc(l.action)}</b> • ${esc(l.module_name)}<br>
                    <small style="color:#94a3b8;">${esc(l.created_at)}${l.record_id ? ' • #' + esc(l.record_id) : ''}</small>
                </div>
            `).join('') + `
                <a href="activity_logs.php" style="color:#38bdf8; text-decoration:none; display:block; margin-top:8px;">View all logs →</a>
            `;
        } catch (e) {
            box.innerHTML = 'Network error';
        }
    };

    window.headerSendAI = async function() {
        const input = document.getElementById('headerAiInput');
        const q = input.value.trim();
        if (!q) return;
        const box = document.getElementById('aiDropBody');

        const userHtml = document.createElement('div');
        userHtml.style.cssText = 'background:linear-gradient(135deg,#2563eb,#4f46e5); color:white; padding:8px 10px; border-radius:10px; margin:8px 0; margin-left:20%;';
        userHtml.innerText = q;
        box.appendChild(userHtml);
        input.value = '';
        box.scrollTop = box.scrollHeight;

        try {
            const res = await fetch('ai-copilot-endpoint.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ query: q })
            });
            const data = await res.json();
            const bot = document.createElement('div');
            bot.style.cssText = 'background:rgba(255,255,255,0.05); padding:8px 10px; border-radius:10px; margin:8px 0;';
            bot.innerText = data.success ? (data.reply || 'No reply') : (data.error || 'Error');
            box.appendChild(bot);
            box.scrollTop = box.scrollHeight;
        } catch (e) {
            const bot = document.createElement('div');
            bot.style.cssText = 'background:rgba(255,255,255,0.05); padding:8px 10px; border-radius:10px; margin:8px 0;';
            bot.innerText = 'Network error';
            box.appendChild(bot);
        }
    };

    document.getElementById('headerAiInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') headerSendAI();
    });

    function esc(t) {
        const d = document.createElement('div');
        d.textContent = t || '';
        return d.innerHTML;
    }
})();
</script>