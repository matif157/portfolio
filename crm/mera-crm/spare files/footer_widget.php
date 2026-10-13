<?php
/**
 * =====================================================
 * FILE: footer_widget.php
 * PURPOSE: Global footer widget
 * - Global search
 * - User profile panel
 * - My activities
 * - AI Copilot
 * - Voice button (placeholder for later)
 * =====================================================
 */

if (!isset($_SESSION['user_id'])) {
    return; // login ke baghair widget mat dikhao
}

$fw_name  = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$fw_role  = htmlspecialchars($_SESSION['role_name'] ?? 'Staff');
$fw_email = htmlspecialchars($currentUser['email'] ?? '');
?>

<style>
/* ========== FOOTER WIDGET STYLES ========== */
#fw-root { font-family: Poppins, Arial, sans-serif; }

#fw-search-btn, #fw-profile-btn, #fw-ai-btn, #fw-voice-btn {
    position: fixed;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    z-index: 99990;
    box-shadow: 0 8px 22px rgba(0,0,0,0.35);
    transition: transform .2s ease;
}
#fw-search-btn:hover, #fw-profile-btn:hover, #fw-ai-btn:hover, #fw-voice-btn:hover {
    transform: scale(1.07);
}

#fw-search-btn { bottom: 24px; right: 260px; background: linear-gradient(135deg,#0ea5e9,#2563eb); }
#fw-profile-btn { bottom: 24px; right: 196px; background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
#fw-voice-btn { bottom: 24px; right: 132px; background: linear-gradient(135deg,#f59e0b,#d97706); }
#fw-ai-btn { bottom: 24px; right: 24px; background: linear-gradient(135deg,#2563eb,#7c3aed); font-size: 22px; }

.fw-panel {
    position: fixed;
    bottom: 90px;
    right: 24px;
    width: 400px;
    max-width: calc(100vw - 24px);
    max-height: 70vh;
    background: rgba(15,23,42,0.97);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 18px;
    display: none;
    flex-direction: column;
    z-index: 99995;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    overflow: hidden;
    color: #e2e8f0;
}
.fw-panel.open { display: flex; }

.fw-head {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(90deg,#1e293b,#0f172a);
}
.fw-head h4 { margin: 0; font-size: 15px; }
.fw-close { background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer; }

.fw-body { padding: 14px; overflow-y: auto; flex: 1; }

.fw-input {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 11px 14px;
    color: white;
    font-size: 13.5px;
    box-sizing: border-box;
}

.fw-item {
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(255,255,255,0.05);
    margin-top: 8px;
    cursor: pointer;
}
.fw-item:hover { background: rgba(255,255,255,0.09); }
.fw-item small { color: #94a3b8; }

.fw-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg,#2563eb,#7c3aed);
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; font-weight: 700; color: white;
}

.fw-ai-msg { margin-bottom: 10px; max-width: 90%; padding: 10px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4; }
.fw-ai-user { margin-left: auto; background: linear-gradient(135deg,#2563eb,#4f46e5); color: white; }
.fw-ai-bot { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.08); }

@media (max-width: 700px) {
    #fw-search-btn { right: 196px; }
    #fw-profile-btn { right: 132px; }
    #fw-voice-btn { right: 68px; width: 46px; height: 46px; }
    #fw-ai-btn { width: 46px; height: 46px; }
}
</style>

<div id="fw-root">
    <!-- Buttons -->
    <button id="fw-search-btn" title="Global Search">🔍</button>
    <button id="fw-profile-btn" title="My Profile">👤</button>
    <button id="fw-voice-btn" title="Voice Assistant (soon)">🎤</button>
    <button id="fw-ai-btn" title="AI Copilot">🤖</button>

    <!-- SEARCH PANEL -->
    <div class="fw-panel" id="fw-search-panel">
        <div class="fw-head">
            <h4>Global Search</h4>
            <button class="fw-close" onclick="fwCloseAll()">×</button>
        </div>
        <div class="fw-body">
            <input class="fw-input" id="fw-search-input" placeholder="Client, invoice, project, user..." oninput="fwDoSearch()">
            <div id="fw-search-results" style="margin-top:10px; color:#94a3b8; font-size:13px;">Type to search...</div>
        </div>
    </div>

    <!-- PROFILE PANEL -->
    <div class="fw-panel" id="fw-profile-panel">
        <div class="fw-head">
            <h4>My Profile</h4>
            <button class="fw-close" onclick="fwCloseAll()">×</button>
        </div>
        <div class="fw-body" id="fw-profile-body">
            <div style="display:flex; gap:14px; align-items:center; margin-bottom:14px;">
                <div class="fw-avatar"><?= strtoupper(substr($fw_name, 0, 1)) ?></div>
                <div>
                    <div style="font-weight:600; font-size:16px;"><?= $fw_name ?></div>
                    <div style="color:#94a3b8; font-size:12px;"><?= $fw_role ?></div>
                    <div style="color:#64748b; font-size:12px;"><?= $fw_email ?></div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px;">
                <button class="fw-item" style="text-align:center; border:none; color:#e2e8f0;" onclick="fwLoadMyActivities()">📋 My Activities</button>
                <button class="fw-item" style="text-align:center; border:none; color:#e2e8f0;" onclick="fwShowAccount()">🔑 Account</button>
            </div>

            <div id="fw-profile-content" style="font-size:13px; color:#cbd5e1;">
                Profile details load yahan honge...
            </div>
        </div>
    </div>

    <!-- AI PANEL -->
    <div class="fw-panel" id="fw-ai-panel">
        <div class="fw-head">
            <div>
                <h4>AI Copilot</h4>
            </div>
            <button class="fw-close" onclick="fwCloseAll()">×</button>
        </div>
        <div class="fw-body" id="fw-ai-messages" style="display:flex; flex-direction:column;">
            <div class="fw-ai-msg fw-ai-bot">Assalam o Alaikum! Main aapka CRM assistant hoon. Clients, invoices, projects ke bare mein pooch sakte ho.</div>
        </div>
        <div style="padding:10px 12px; border-top:1px solid rgba(255,255,255,0.08); display:flex; gap:8px;">
            <input class="fw-input" id="fw-ai-input" placeholder="Sawal likhein..." style="flex:1;">
            <button onclick="fwSendAI()" style="width:44px; border:none; border-radius:12px; background:linear-gradient(135deg,#2563eb,#7c3aed); color:white; cursor:pointer;">➤</button>
        </div>
    </div>
</div>

<script>
function fwCloseAll() {
    document.querySelectorAll('.fw-panel').forEach(p => p.classList.remove('open'));
}
function fwOpen(id) {
    fwCloseAll();
    document.getElementById(id).classList.add('open');
}

document.getElementById('fw-search-btn').onclick = () => {
    fwOpen('fw-search-panel');
    document.getElementById('fw-search-input').focus();
};
document.getElementById('fw-profile-btn').onclick = () => {
    fwOpen('fw-profile-panel');
    fwLoadProfileSummary();
};
document.getElementById('fw-ai-btn').onclick = () => fwOpen('fw-ai-panel');
document.getElementById('fw-voice-btn').onclick = () => alert('Urdu Voice Assistant next step mein add hoga.');

// ===== SEARCH =====
let fwSearchTimer = null;
function fwDoSearch() {
    clearTimeout(fwSearchTimer);
    fwSearchTimer = setTimeout(async () => {
        const q = document.getElementById('fw-search-input').value.trim();
        const box = document.getElementById('fw-search-results');
        if (q.length < 2) {
            box.innerHTML = 'Type at least 2 characters...';
            return;
        }
        box.innerHTML = 'Searching...';
        try {
            const res = await fetch('footer_api.php?action=search&q=' + encodeURIComponent(q));
            const data = await res.json();
            if (!data.success) {
                box.innerHTML = data.error || 'Error';
                return;
            }
            if (!data.results.length) {
                box.innerHTML = 'No results found';
                return;
            }
            box.innerHTML = data.results.map(r => `
                <div class="fw-item" onclick="window.location.href='${r.link}'">
                    <strong>${fwEsc(r.title)}</strong><br>
                    <small>${fwEsc(r.type)} • ${fwEsc(r.subtitle || '')}</small>
                </div>
            `).join('');
        } catch (e) {
            box.innerHTML = 'Network error';
        }
    }, 300);
}

// ===== PROFILE =====
async function fwLoadProfileSummary() {
    const box = document.getElementById('fw-profile-content');
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
            <div style="line-height:1.8;">
                <div><b>Username:</b> ${fwEsc(u.username)}</div>
                <div><b>Email:</b> ${fwEsc(u.email)}</div>
                <div><b>Designation:</b> ${fwEsc(u.designation || '-')}</div>
                <div><b>Phone:</b> ${fwEsc(u.phone || '-')}</div>
                <div><b>Last Login:</b> ${fwEsc(u.last_login || 'Never')}</div>
            </div>
            <div style="margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <div class="fw-item" style="text-align:center;"><b>${u.invoice_count || 0}</b><br><small>Invoices</small></div>
                <div class="fw-item" style="text-align:center;"><b>${u.activity_count || 0}</b><br><small>Activities</small></div>
            </div>
        `;
    } catch (e) {
        box.innerHTML = 'Network error';
    }
}

async function fwLoadMyActivities() {
    const box = document.getElementById('fw-profile-content');
    box.innerHTML = 'Loading activities...';
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
            <div class="fw-item">
                <strong>${fwEsc(l.action)}</strong> • ${fwEsc(l.module_name)}<br>
                <small>${fwEsc(l.created_at)} ${l.record_id ? '• #' + fwEsc(l.record_id) : ''}</small>
            </div>
        `).join('');
    } catch (e) {
        box.innerHTML = 'Network error';
    }
}

function fwShowAccount() {
    document.getElementById('fw-profile-content').innerHTML = `
        <div style="line-height:1.8;">
            <div><b>Password change</b></div>
            <div style="color:#94a3b8; font-size:12px; margin:6px 0 10px;">Security ke liye password change alag secure page se hoga.</div>
            <a href="edit_user.php?id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" style="color:#38bdf8;">Open Account Settings →</a>
        </div>
    `;
}

// ===== AI =====
async function fwSendAI() {
    const input = document.getElementById('fw-ai-input');
    const q = input.value.trim();
    if (!q) return;
    const box = document.getElementById('fw-ai-messages');

    const userDiv = document.createElement('div');
    userDiv.className = 'fw-ai-msg fw-ai-user';
    userDiv.innerText = q;
    box.appendChild(userDiv);
    input.value = '';
    box.scrollTop = box.scrollHeight;

    try {
        const res = await fetch('ai-copilot-endpoint.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ query: q })
        });
        const data = await res.json();
        const botDiv = document.createElement('div');
        botDiv.className = 'fw-ai-msg fw-ai-bot';
        botDiv.innerText = data.success ? (data.reply || 'No reply') : (data.error || 'Error');
        box.appendChild(botDiv);
        box.scrollTop = box.scrollHeight;
    } catch (e) {
        const botDiv = document.createElement('div');
        botDiv.className = 'fw-ai-msg fw-ai-bot';
        botDiv.innerText = 'Network error';
        box.appendChild(botDiv);
    }
}

document.getElementById('fw-ai-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') fwSendAI();
});

function fwEsc(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}
</script>