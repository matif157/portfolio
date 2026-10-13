<style type="text/css">
    .dash-submenu {
        display: none;
    }

    .dash-item.active .dash-submenu {
        display: block;
    }

    .dash-sidebar .main-logo {
        justify-content: center;
        min-height: 100px;
        max-height: 100px;
        min-width: 236px;
    }

    .ps__thumb-y {
        display: none;
    }

    .dash-sidebar .main-logo a img {
        width: fit-content;
        height: 188px;
        object-fit: contain;
        max-width: -webkit-fill-available;
        max-width: -moz-available;
        max-height: -webkit-fill-available;
        max-height: -moz-available;
        margin: 0 auto;
        display: block;
    }

    .tooltip1 {
        position: relative;
        display: inline-block;
        opacity: 1 !important;
        border-bottom: 1px dotted black;
    }

    .tooltip1 .tooltiptext1 {
        visibility: hidden;
        width: 125px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px 0;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -60px;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .tooltip1:hover .tooltiptext1 {
        visibility: visible;
        opacity: 1;
    }

    body.sidebar-collapsed .dash-sidebar {
        width: 80px;
        min-width: 80px;
        z-index: 1020;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo {
        min-width: 80px;
        padding: 0 10px;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo a.b-brand {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo a.b-brand-collapsed {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 150%;
        height: 100%;
        margin-left: 20px;
    }

    body:not(.sidebar-collapsed) .dash-sidebar .main-logo a.b-brand-collapsed {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo a.b-brand-collapsed img {
        width: 100px !important;
        height: 100px !important;
        object-fit: contain;
        border-radius: 8px;
    }

    body.sidebar-collapsed .dash-sidebar .dash-mtext,
    body.sidebar-collapsed .dash-sidebar .dash-arrow,
    body.sidebar-collapsed .dash-sidebar .badge {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .dash-submenu {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .dash-link {
        justify-content: center;
        padding: 10px;
        text-align: center;
    }

    body.sidebar-collapsed .dash-sidebar .dash-micon {
        margin-right: 0 !important;
        font-size: 22px;
        display: block;
    }

    body.sidebar-collapsed .dash-container {
        margin-left: 80px;
        width: calc(100% - 80px);
    }

    body.sidebar-collapsed .dash-sidebar::-webkit-scrollbar {
        width: 0px;
        background: transparent;
    }

    /* ==========================================
       NEW: Sidebar Header & Toggle Styles
       ========================================== */

    .sidebar-header {
        height: 50px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 15px;
        border-bottom: 1px solid #f0f0f0;
        background: #fff;
        box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }

    #sidebar-toggle {
        position: relative;
        top: auto;
        right: auto;
        z-index: 1000;
        background: #fff;
        border: 1px solid #e1e1e1;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        color: #000;
        transition: all 0.3s;
    }

    #sidebar-toggle:hover {
        background: #f8f9fa;
        transform: scale(1.05);
    }

    /* Center the toggle when sidebar is collapsed */
    body.sidebar-collapsed .sidebar-header {
        justify-content: center;
        padding-right: 0;
    }

    body.sidebar-collapsed .sidebar-header #sidebar-toggle {
        margin: 0;
    }

    /* ========================================== */

    .sidebar-popover {
        position: fixed;
        left: 80px;
        background: #fff;
        border: 1px solid #e1e1e1;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        z-index: 9999 !important;
        border-radius: 4px;
        padding: 0;
        display: none;
        min-width: 240px;
        max-width: 300px;
        white-space: nowrap;
    }

    .sidebar-popover::before {
        content: "";
        position: absolute;
        top: 15px;
        left: -6px;
        width: 12px;
        height: 12px;
        background: #fff;
        transform: rotate(45deg);
        border-left: 1px solid #e1e1e1;
        border-bottom: 1px solid #e1e1e1;
    }

    .sidebar-popover .dash-link {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        color: #333 !important;
        text-decoration: none;
        text-align: left;
        justify-content: flex-start;
        transition: background 0.2s;
        font-size: 14px;
        border-bottom: 1px solid #f9f9f9;
        opacity: 1 !important;
        visibility: visible !important;
        width: 100%;
        box-sizing: border-box;
    }

    .sidebar-popover .dash-link:last-child {
        border-bottom: none;
    }

    .sidebar-popover .dash-link i {
        margin-right: 12px;
        font-size: 16px;
        color: #555;
        width: 20px;
        text-align: center;
    }

    .sidebar-popover .dash-link:hover {
        background: #f8f9fa;
        color: #007bff !important;
    }

    .sidebar-popover .dash-link:hover i {
        color: #007bff;
    }

    .sidebar-popover .popover-header {
        font-weight: 700;
        padding: 12px 20px;
        border-bottom: 1px solid #eee;
        background: #f1f1f1;
        color: #000 !important;
        font-size: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dash-sidebar,
    .dash-container,
    .dash-micon {
        transition: all 0.3s ease;
    }

    #see-more-trigger {
        color: #f5a623 !important;
        font-weight: 600;
    }

    #see-more-trigger .dash-micon i,
    #see-more-trigger .dash-arrow i {
        color: #f5a623 !important;
    }

    #see-more-trigger:hover {
        background: rgba(245, 166, 35, 0.1) !important;
    }

    /* Non-active (default) sidebar items shown with low-opacity navy background */
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link {
        background: rgba(37, 33, 97, 0.12) !important;
        color: #252161 !important;
    }

    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon svg {
        color: #252161 !important;
    }

    /* Override theme green (#0CAF60) with logo color (#252161) on active/hover only */
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link {
        background: linear-gradient(141.55deg, #252161 3.46%, #252161 99.86%), #252161 !important;
        color: #fff !important;
        box-shadow: 0 5px 7px -1px rgba(37, 33, 97, 0.3) !important;
    }

    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-mtext {
        color: #fff !important;
    }

    /* Active/hover icon stays navy so it shows inside its white icon box */
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon svg,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon svg,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon svg,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon svg {
        color: #252161 !important;
    }

    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item::before,
    .dash-sidebar .dash-item .dash-submenu .dash-item::before {
        border-right-color: #252161 !important;
    }

    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item.active>.dash-link,
    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item:active>.dash-link,
    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item:focus>.dash-link,
    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item:hover>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item.active>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item:active>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item:focus>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item:hover>.dash-link {
        color: #252161 !important;
    }

    /* Fix: long labels (e.g. "Product Renewals") left no room for the
       float:right chevron on the same line, so it dropped onto the next
       menu item. Lay the link out as a flex row instead so the arrow
       always stays pinned to the end of its own row. */
    body:not(.sidebar-collapsed) .dash-sidebar .dash-navbar>.dash-item>.dash-link {
        display: flex;
        align-items: center;
    }

    body:not(.sidebar-collapsed) .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-mtext {
        flex: 1 1 auto;
        min-width: 0;
    }

    body:not(.sidebar-collapsed) .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-arrow {
        float: none;
        flex-shrink: 0;
        margin-left: 8px;
    }
</style>
<?php
/* ---- Custom cursor (global, context-aware) ---- */
$curEnabled = '0'; $curStyle = 'dot'; $curColor = '#1456b8'; $curSize = 10;
@$cur_marker_q = mysqli_query($con, "SELECT marker_key, marker_value FROM settings_marker WHERE marker_key IN ('cursor_enabled','cursor_style','cursor_color','cursor_size')");
if ($cur_marker_q) {
    while ($_cm = mysqli_fetch_assoc($cur_marker_q)) {
        if ($_cm['marker_key'] === 'cursor_enabled') $curEnabled = $_cm['marker_value'];
        if ($_cm['marker_key'] === 'cursor_style') $curStyle = $_cm['marker_value'];
        if ($_cm['marker_key'] === 'cursor_color') $curColor = $_cm['marker_value'];
        if ($_cm['marker_key'] === 'cursor_size') $curSize = (int) $_cm['marker_value'];
    }
}
$curSize = max(4, min(24, (int) $curSize));
$curShow = ($curEnabled === '1');
?>
<?php if ($curShow): ?>
<style type="text/css">
    .cur-global, .cur-global * { cursor: none !important; }
    #curDot, #curRing { display: block; }
</style>
<?php endif; ?>
<script>
    (function () {
        var CUR_ENABLED = <?= $curShow ? 'true' : 'false' ?>;
        var CUR_STYLE = <?= json_encode($curStyle) ?>;
        var CUR_COLOR = <?= json_encode($curColor) ?>;
        var CUR_SIZE = <?= (int) $curSize ?>;
        /* On the settings page the live local preview governs; skip global. */
        if (document.body && document.body.dataset.curPreview) return;

        var dot, ring, raf = null, tx = 0, ty = 0, cx = 0, cy = 0, hovering = false;
        function build() {
            if (!dot) {
                dot = document.createElement('div'); dot.id = 'curDot';
                ring = document.createElement('div'); ring.id = 'curRing';
                document.body.appendChild(dot); document.body.appendChild(ring);
            }
            dot.style.cssText = 'position:fixed;top:0;left:0;width:' + CUR_SIZE + 'px;height:' + CUR_SIZE + 'px;margin:-' + (CUR_SIZE / 2) + 'px 0 0 -' + (CUR_SIZE / 2) + 'px;border-radius:50%;background:' + CUR_COLOR + ';pointer-events:none;z-index:99999;transform:translate(-100px,-100px)';
            ring.style.cssText = 'position:fixed;top:0;left:0;width:' + (CUR_SIZE * 1.8) + 'px;height:' + (CUR_SIZE * 1.8) + 'px;margin:-' + (CUR_SIZE * 0.9) + 'px 0 0 -' + (CUR_SIZE * 0.9) + 'px;border-radius:50%;border:2px solid ' + CUR_COLOR + ';pointer-events:none;z-index:99999;opacity:.55;transition:transform .12s ease-out,width .15s,height .15s,margin .15s,opacity .15s;transform:translate(-100px,-100px)';
            dot.style.display = CUR_ENABLED ? 'block' : 'none';
            ring.style.display = (CUR_ENABLED && CUR_STYLE !== 'dot') ? 'block' : 'none';
            if (!CUR_ENABLED) return;
            /* context-aware ring: grow/shrink over interactive elements */
            document.addEventListener('mouseover', function (e) {
                hovering = !!(e.target.closest && e.target.closest('a, button, select, input, textarea, [role=button], .btn, .dash-link, .switch'));
            });
            document.addEventListener('mouseout', function () { hovering = false; });
        }
        window.addEventListener('mousemove', function (e) {
            tx = e.clientX; ty = e.clientY;
            if (!dot) return;
            if (!raf) {
                raf = requestAnimationFrame(function () {
                    cx = tx; cy = ty;
                    if (dot) dot.style.transform = 'translate(' + cx + 'px,' + cy + 'px)';
                    if (ring && CUR_STYLE !== 'dot') {
                        if (hovering) {
                            ring.style.width = (CUR_SIZE * 2.6) + 'px';
                            ring.style.height = (CUR_SIZE * 2.6) + 'px';
                            ring.style.margin = '-' + (CUR_SIZE * 1.3) + 'px 0 0 -' + (CUR_SIZE * 1.3) + 'px';
                            ring.style.opacity = '1';
                            ring.style.background = 'rgba(20,86,184,.12)';
                        } else {
                            ring.style.width = (CUR_SIZE * 1.8) + 'px';
                            ring.style.height = (CUR_SIZE * 1.8) + 'px';
                            ring.style.margin = '-' + (CUR_SIZE * 0.9) + 'px 0 0 -' + (CUR_SIZE * 0.9) + 'px';
                            ring.style.opacity = '.55';
                            ring.style.background = 'transparent';
                        }
                        ring.style.transform = 'translate(' + cx + 'px,' + cy + 'px)';
                    }
                    raf = null;
                });
            }
        });
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build);
        else build();
        if (CUR_ENABLED) document.body.classList.add('cur-global');
        if (!CUR_ENABLED) {
            /* remove any leftover global cursor elements */
            var d2 = document.getElementById('curDot'); if (d2) d2.remove();
            var r2 = document.getElementById('curRing'); if (r2) r2.remove();
        }
    })();
</script>

<?php
/* ---- AI Assistant (global floating chat) ---- */
if (@file_exists(__DIR__ . '/ai_helper.php')) {
    include_once __DIR__ . '/ai_helper.php';
}
$aiShowChat = false;
if (function_exists('ai_can_use') && function_exists('ai_get')) {
    $aiShowChat = ai_can_use() && ai_get('ai_enabled', '1') === '1';
}
if ($aiShowChat):
?>
<style type="text/css">
    #aiFab {
        position: fixed; right: 22px; bottom: 22px; z-index: 998;
        width: 56px; height: 56px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #1456b8, #3b82f6); color: #fff;
        box-shadow: 0 6px 18px rgba(20, 86, 184, .4); display: flex; align-items: center; justify-content: center;
        font-size: 24px; transition: transform .15s;
    }
    #aiFab:hover { transform: scale(1.08); }
    #aiDrawer {
        position: fixed; right: 20px; bottom: 90px; width: 400px; max-width: calc(100vw - 40px);
        height: 560px; max-height: calc(100vh - 140px); z-index: 999;
        background: #fff; border-radius: 16px; box-shadow: 0 18px 50px rgba(0,0,0,.25);
        display: none; flex-direction: column; overflow: hidden; border: 1px solid #e8ecf1;
    }
    #aiDrawer.open { display: flex; }
    #aiHead { background: linear-gradient(120deg, #0f172a, #1456b8); color: #fff; padding: 14px 16px; display: flex; align-items: center; gap: 10px; }
    #aiHead b { flex: 1; }
    #aiHead .ai-x { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; line-height: 1; }
    #aiBody { flex: 1; padding: 14px; overflow-y: auto; background: #f7f9fc; }
    .ai-msg { margin-bottom: 12px; display: flex; }
    .ai-msg.bot { justify-content: flex-start; }
    .ai-msg.user { justify-content: flex-end; }
    .ai-bubble { max-width: 88%; padding: 9px 12px; border-radius: 12px; font-size: 13px; white-space: pre-wrap; word-break: break-word; }
    .ai-msg.bot .ai-bubble { background: #fff; border: 1px solid #e8ecf1; color: #1e293b; }
    .ai-msg.user .ai-bubble { background: #1456b8; color: #fff; }
    .ai-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; background: #fff; }
    .ai-table th, .ai-table td { border: 1px solid #dde3ec; padding: 5px 6px; text-align: left; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ai-table th { background: #eef2f7; }
    .ai-tools { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
    .ai-tools button, .ai-confirm button { border: none; border-radius: 8px; padding: 6px 10px; font-size: 12px; cursor: pointer; color: #fff; }
    .ai-csv { background: #16a34a; } .ai-pdf { background: #dc2626; } .ai-confirm { background: #1456b8; } .ai-cancel { background: #64748b !important; }
    .ai-preview { background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; margin-top: 8px; }
    .ai-preview h6 { margin: 0 0 8px; color: #1456b8; }
    .ai-preview table { width: 100%; font-size: 12px; border-collapse: collapse; }
    .ai-preview td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
    .ai-confirmwrap { display: flex; gap: 8px; margin-top: 10px; }
    #aiFoot { padding: 10px; border-top: 1px solid #e8ecf1; background: #fff; }
    #aiRow { display: flex; align-items: flex-end; gap: 8px; }
    #aiIn { flex: 1; border: 1px solid #cbd5e1; border-radius: 10px; padding: 9px 10px; font-size: 13px; resize: none; max-height: 96px; }
    .ai-icobtn { background: none; border: none; font-size: 22px; cursor: pointer; color: #1456b8; line-height: 1; padding: 2px; }
    .ai-icobtn.rec { color: #dc2626; }
    .ai-mini { display: flex; gap: 6px; margin-top: 8px; }
    .ai-mini button { border: 1px solid #cbd5e1; background: #fff; color: #334155; border-radius: 20px; padding: 4px 10px; font-size: 11px; }
    #aiImgName { font-size: 11px; color: #64748b; margin-top: 4px; }
</style>

<div id="aiFab" title="AI Assistant"><i class="ti ti-robot"></i></div>

<div id="aiDrawer">
    <div id="aiHead">
        <i class="ti ti-robot"></i>
        <b>AI Assistant</b>
        <button class="ai-x" type="button" onclick="aiToggle(false)">&times;</button>
    </div>
    <div id="aiBody">
        <div class="ai-msg bot"><div class="ai-bubble">Hello! I can search your CRM data, generate Excel/PDF/docs, read an image (OCR), and (with your approval) add clients or notes. Try the quick actions below or just type a question.</div></div>
        <div class="ai-mini" id="aiQuick">
            <button type="button" onclick="aiQuick('List 5 active clients with their phone and email.')">List clients</button>
            <button type="button" onclick="aiQuick('Sales tax clients for 2025')">Sales-tax clients 2025</button>
            <button type="button" onclick="aiQuick('Show clients with unpaid invoices and their balances.')">Unpaid invoices</button>
        </div>
        <div id="aiMsgs"></div>
    </div>
    <div id="aiFoot">
        <div id="aiImgName"></div>
        <div id="aiRow">
            <button class="ai-icobtn" id="aiMic" title="Voice" onclick="aiVoice()"><i class="ti ti-microphone"></i></button>
            <textarea id="aiIn" rows="1" placeholder="Ask the AI, e.g. 'add a client named John Doe in New York'" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();aiSend();}"></textarea>
            <label class="ai-icobtn" title="Upload image (OCR)"><i class="ti ti-photo"></i><input type="file" id="aiImg" accept="image/*" style="display:none" onchange="aiOcr(this)"></label>
            <button class="ai-icobtn" title="Send" onclick="aiSend()"><i class="ti ti-send"></i></button>
        </div>
    </div>
</div>

<script>
    (function () {
        window.aiState = { pending: null }; // {type,fields,body}
        window.__aiLatestSql = '';
        window.aiToggle = function (open) {
            var d = document.getElementById('aiDrawer');
            if (typeof open === 'undefined') open = !d.classList.contains('open');
            d.classList.toggle('open', open);
            if (open) setTimeout(function () { var i = document.getElementById('aiIn'); if (i) i.focus(); }, 60);
        };
        document.getElementById('aiFab').addEventListener('click', function () { aiToggle(); });
    })();

    function aiAddMsg(role, html) {
        var wrap = document.createElement('div');
        wrap.className = 'ai-msg ' + role;
        var b = document.createElement('div');
        b.className = 'ai-bubble';
        b.innerHTML = html;
        wrap.appendChild(b);
        document.getElementById('aiMsgs').appendChild(wrap);
        var body = document.getElementById('aiBody');
        body.scrollTop = body.scrollHeight;
        return wrap;
    }
    function aiQuick(q) { document.getElementById('aiIn').value = q; aiSend(); }
    function aiSend() {
        var inp = document.getElementById('aiIn');
        var msg = inp.value.trim();
        if (!msg) return;
        aiAddMsg('user', msg.replace(/</g, '&lt;'));
        inp.value = '';
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            aiAddMsg('bot', aiLocalClient(msg)); // offline fallback
            return;
        }
        aiAddMsg('bot', 'Thinking...');
        var fd = new FormData();
        fd.append('action', 'chat');
        fd.append('message', msg);
        fetch('ai_ajax.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                aiRemoveLastThinking();
                if (!res.ok) { aiAddMsg('bot', 'Error: ' + (res.error || 'Unknown')); return; }
                aiHandle(res);
            })
            .catch(function () { aiRemoveLastThinking(); aiAddMsg('bot', aiLocalClient(msg)); });
    }
    /* Offline local responder (basic queries only; mirrors server ai_local_answer). */
    function aiLocalClient(msg) {
        var m = (msg || '').toLowerCase().trim();
        if (/^(hi|hello|hey|good (morning|afternoon|evening)|salam|yo)\b/.test(m)) return "Hello! I'm in offline mode right now, so I can only answer basic questions. Try 'help' to see what I can do.";
        if (m.indexOf('how are you') !== -1) return "I'm running offline but ready to help with basic questions. Reconnect for live data and AI answers.";
        if (m.indexOf('who are you') !== -1 || m.indexOf('what are you') !== -1 || m.indexOf('your name') !== -1) return "I'm the Syed Pro CRM AI assistant. Offline I can do basics; online I can search clients/invoices/projects, export Excel/PDF, OCR images, and add clients/notes with your approval.";
        if (/(thanks|thank you|thx|cheers)/.test(m)) return "You're welcome! (Note: I'm offline right now — reconnect for live data help.)";
        if (/\b(help|what can you do|capabilit|features|commands)\b/.test(m)) return "I'm in OFFLINE mode, so basic questions only.\n- Say 'hi' / 'hello'\n- Ask 'who are you' / 'what can you do'\n- Say 'open invoices', 'open clients', 'open projects', 'open settings', 'open dashboard' to navigate\n- Say 'thank you'\n\nOnline I can also search your data, export Excel/PDF, OCR images, and add clients/notes (with confirmation).";
        if (/\b(offline|internet|online|connection|network)\b/.test(m)) return "Right now I'm working offline (the AI service can't be reached, usually no internet). I can still answer basic questions and open pages; live data lookups, OCR, exports, and AI answers need internet.";
        var nav = m.match(/\bopen (invoices?|clients?|projects?|services?|products?|memberships?|dashboard|home|settings)\b/);
        if (nav) { var p = nav[1]; var u = ({invoice:'invoice_billings.php',invoices:'invoice_billings.php',client:'view_clients.php',clients:'view_clients.php',project:'projects.php',projects:'projects.php',service:'memberships.php',services:'memberships.php',product:'memberships.php',products:'memberships.php',membership:'memberships.php',memberships:'memberships.php',dashboard:'index.php',home:'index.php',settings:'ai_settings.php'})[p]||'index.php'; return 'Opening ' + p + ' (offline shortcut).\n→ /' + u; }
        if (/\b(add|create|new|insert)\b/.test(m)) return "Adding records needs internet (and your confirmation). I'm offline now, so I can't do that — reconnect and I'll help.";
        if (/\b(list|show|find|search|how many|count|get|total|balance|unpaid|invoice|client|project|sales|tax)\b/.test(m)) return "That's a data question, which needs the online AI. I'm offline now; reconnect and I'll look it up. Offline I can still do greetings, navigation, and 'what can you do'.";
        return "I'm in offline mode, so basic questions only (greetings, navigation, 'what can you do'). Type 'help' to see offline options. Reconnect for AI answers and live data.";
    }
    function aiRemoveLastThinking() {
        var body = document.getElementById('aiMsgs');
        var last = body.lastElementChild;
        if (last && last.textContent.indexOf('Thinking...') !== -1) last.remove();
    }
    function aiHandle(res) {
        if (res.type === 'newclient') {
            aiAddMsg('bot', res.text);
            aiShowClientPreview(res.fields);
        } else if (res.type === 'note') {
            aiAddMsg('bot', res.text);
            aiShowNotePreview(res.member_id, res.text_note);
        } else if (res.type === 'table') {
            var html = (res.text || '').replace(/</g, '&lt;') + '<br>' + aiTable(res.rows, res.sql);
            aiAddMsg('bot', html);
        } else if (res.type === 'ocr') {
            aiAddMsg('bot', (res.summary || 'OCR result').replace(/</g, '&lt;'));
            if (res.fields && Object.keys(res.fields).length) {
                aiShowClientPreview(res.fields, true);
            }
        } else {
            aiAddMsg('bot', (res.text || '').replace(/</g, '&lt;'));
        }
    }
    function aiTable(rows, sql) {
        if (!rows || !rows.length) return '<div style="font-size:12px;color:#64748b">No matching records.</div>';
        var h = Object.keys(rows[0]);
        var html = '<div style="overflow:auto;max-height:260px"><table class="ai-table"><thead><tr>';
        h.forEach(function (k) { html += '<th>' + k.replace(/</g, '&lt;') + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(function (r) {
            html += '<tr>';
            h.forEach(function (k) {
                var v = r[k] === null ? '' : String(r[k]);
                html += '<td>' + v.replace(/</g, '&lt;') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        window.__aiLatestSql = sql;
        html += '<div class="ai-tools">';
        html += '<button class="ai-csv" onclick="aiExportCsv()">Download CSV</button>';
        html += '<button class="ai-pdf" onclick="aiExportPdf()">Download PDF</button>';
        html += '</div>';
        return html;
    }
    function aiExportCsv() {
        var fd = new FormData(); fd.append('action', 'export_csv'); fd.append('sql', window.__aiLatestSql || '');
        aiDoBlob(fd, 'ai_export.csv');
    }
    function aiExportPdf() {
        var fd = new FormData(); fd.append('action', 'export_pdf'); fd.append('sql', window.__aiLatestSql || ''); fd.append('title', 'AI Export');
        aiDoBlob(fd, 'ai_export.pdf');
    }
    function aiDoBlob(fd, name) {
        fetch('ai_ajax.php', { method: 'POST', body: fd })
            .then(function (r) {
                var ct = r.headers.get('Content-Type') || '';
                if (ct.indexOf('application/json') !== -1) return r.json().then(function (j) { aiAddMsg('bot', 'Error: ' + (j.error || 'Unknown')); return null; });
                return r.blob();
            })
            .then(function (b) { if (b) aiDownload(b, name); });
    }
    function aiDownload(blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a'); a.href = url; a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    }
    function aiShowClientPreview(fields, editable) {
        var f = fields || {};
        aiState.pending = { type: 'client', fields: f };
        var html = '<div class="ai-preview"><h6>Verify before inserting</h6>';
        html += '<table>';
        html += '<tr><td><b>First name</b></td><td>' + (f.name || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Last name</b></td><td>' + (f.lname || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Business</b></td><td>' + (f.bus_name || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Phone</b></td><td>' + (f.phone || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Cell</b></td><td>' + (f.cell_ph || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Email</b></td><td>' + (f.prm_email || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Address</b></td><td>' + (f.address || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>City</b></td><td>' + (f.city || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>State</b></td><td>' + (f.state || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '<tr><td><b>Zip</b></td><td>' + (f.zip || '').replace(/</g, '&lt;') + '</td></tr>';
        html += '</table>';
        html += '<div class="ai-confirmwrap"><button class="ai-confirm" onclick="aiConfirmClient()">Confirm &amp; Insert</button><button class="ai-cancel" onclick="aiCancelPending()">Cancel</button></div>';
        html += '</div>';
        aiAddMsg('bot', html);
    }
    function aiShowNotePreview(mid, note) {
        aiState.pending = { type: 'note', member_id: mid, text_note: note };
        var html = '<div class="ai-preview"><h6>Add note to client #' + mid + '</h6>';
        html += '<div style="font-size:12px;margin-bottom:8px">' + note.replace(/</g, '&lt;') + '</div>';
        html += '<div class="ai-confirmwrap"><button class="ai-confirm" onclick="aiConfirmNote()">Confirm &amp; Add</button><button class="ai-cancel" onclick="aiCancelPending()">Cancel</button></div>';
        html += '</div>';
        aiAddMsg('bot', html);
    }
    function aiCanWrite() { return true; } // backend enforces permission + confirm
    function aiConfirmClient() {
        var p = aiState.pending; if (!p) return;
        var fd = new FormData();
        fd.append('action', 'preview_client');
        fd.append('fields', JSON.stringify(p.fields));
        fetch('ai_ajax.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { aiAddMsg('bot', 'Error: ' + (res.error || '')); return; }
                var f2 = new FormData();
                f2.append('action', 'confirm_client');
                f2.append('confirmed', '1');
                f2.append('fields', JSON.stringify(res.preview));
                return fetch('ai_ajax.php', { method: 'POST', body: f2 });
            })
            .then(function (r) { return r ? r.json() : null; })
            .then(function (res) {
                if (res && res.ok) {
                    aiState.pending = null;
                    var name = (p.fields.name || 'client').replace(/</g, '&lt;');
                    aiAddMsg('bot', 'Done! ' + res.message.replace(/</g, '&lt;') + '<br><a target="_blank" href="client_details.php?id=' + res.member_id + '" style="color:#1456b8">Open client #' + res.member_id + '</a>');
                } else if (res) { aiAddMsg('bot', 'Error: ' + (res.error || '')); }
            });
    }
    function aiConfirmNote() {
        var p = aiState.pending; if (!p) return;
        var fd = new FormData();
        fd.append('action', 'preview_note');
        fd.append('member_id', p.member_id);
        fd.append('text_note', p.text_note);
        fetch('ai_ajax.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { aiAddMsg('bot', 'Error: ' + (res.error || '')); return; }
                var f2 = new FormData();
                f2.append('action', 'confirm_note');
                f2.append('confirmed', '1');
                f2.append('member_id', res.preview.member_id);
                f2.append('text_note', res.preview.note);
                return fetch('ai_ajax.php', { method: 'POST', body: f2 });
            })
            .then(function (r) { return r ? r.json() : null; })
            .then(function (res) {
                if (res && res.ok) { aiState.pending = null; aiAddMsg('bot', res.message.replace(/</g, '&lt;')); }
                else if (res) { aiAddMsg('bot', 'Error: ' + (res.error || '')); }
            });
    }
    function aiCancelPending() { aiState.pending = null; aiAddMsg('bot', 'Cancelled.'); }
    function aiOcr(input) {
        var file = input.files[0]; if (!file) return;
        document.getElementById('aiImgName').textContent = 'OCR: ' + file.name;
        var reader = new FileReader();
        reader.onload = function () {
            var base64 = reader.result.split(',')[1];
            var fd = new FormData();
            fd.append('action', 'ocr');
            fd.append('image', base64);
            fd.append('mime', file.type || 'image/png');
            aiAddMsg('bot', 'Reading image...');
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    aiRemoveLastThinking();
                    document.getElementById('aiImgName').textContent = '';
                    if (!res.ok) { aiAddMsg('bot', 'OCR error: ' + (res.error || '')); return; }
                    aiHandle(res);
                })
                .catch(function () { aiAddMsg('bot', 'OCR network error.'); });
        };
        reader.readAsDataURL(file);
        input.value = '';
    }
    function aiVoice() {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) { aiAddMsg('bot', 'Voice is not supported in this browser.'); return; }
        var rec = new SR();
        rec.lang = 'en-US';
        rec.interimResults = true;
        var mic = document.getElementById('aiMic');
        mic.classList.add('rec');
        var lastInterim = '';
        aiAddMsg('bot', 'Listening... speak now. (text appears below as you talk)');
        rec.onresult = function (e) {
            var spoken = '';
            for (var i = 0; i < e.results.length; i++) spoken += e.results[i][0].transcript;
            var inp = document.getElementById('aiIn');
            inp.value = spoken;
            lastInterim = spoken;
        };
        rec.onerror = function () { mic.classList.remove('rec'); aiAddMsg('bot', 'Voice error — you can type instead. The live text is shown above.'); };
        rec.onend = function () {
            mic.classList.remove('rec');
            var inp = document.getElementById('aiIn');
            var final = lastInterim || inp.value;
            if (final && final.trim()) { aiSend(); }
        };
        rec.start();
    }
</script>
<?php endif; ?>

<nav class="dash-sidebar light-sidebar transprent-bg">
    <div class="navbar-wrapper">


        <div class="sidebar-header">
            <div id="sidebar-toggle">
                <i class="ti ti-chevron-left" id="toggle-icon"></i>
            </div>
        </div>

        <div class="m-header main-logo" style="position: relative;">
            <a href="index.php" class="b-brand">
                <img src="storage/uploads/logo/logo-dark.png" alt="" class="logo logo-lg" />
            </a>
            <a href="index.php" class="b-brand-collapsed">
                <img src="storage/uploads/logo/logoin.png" alt="" />
            </a>


        </div>

        <div class="navbar-content">
            <ul class="dash-navbar">

                <!--------------------- Start Dashboard ----------------------------------->
                <li class="dash-item dash-hasmenu ">
                    <a href="index.php" class="dash-link ">
                        <span class="dash-micon"><i class="ti ti-home"></i></span>
                        <span class="dash-mtext">Dashboard</span>
                    </a>
                </li>

                <!-- <li class="dash-item dash-hasmenu">
                    <a href="recycle_bin.php" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-trash"></i></span>
                        <span class="dash-mtext">Recycle Bin</span>
                    </a>
                </li> -->



                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 171) { ?>
                    <!--------------------- See More ----------------------------------->
                    <li class="dash-item dash-hasmenu" id="see-more-menu">
                        <a href="#" class="dash-link" id="see-more-trigger">
                            <span class="dash-micon"><i class="ti ti-dots-circle-horizontal"></i></span>
                            <span class="dash-mtext">See More <i class="ti ti-lock" style="font-size:12px;opacity:0.6;"></i></span>
                            <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="dash-submenu">
                            <li class="dash-item">
                                <a href="ai.php" class="dash-link">
                                    <i class="ti ti-brain"></i> AI Assistant
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="bookeeping.php" class="dash-link">
                                    <i class="ti ti-books"></i> Bookkeeping
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="curdir.php" class="dash-link">
                                    <i class="ti ti-folder-cog"></i> Cur Dir
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="Db_Backup_Cli.php" class="dash-link">
                                    <i class="ti ti-database-export"></i> DB Backup
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="dbswitch.php" class="dash-link">
                                    <i class="ti ti-database-cog"></i> DB Switcher
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="dbswitcher_updater.php" class="dash-link">
                                    <i class="ti ti-database-plus"></i> DB Switcher Updater
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="manila_edit.php" class="dash-link">
                                    <i class="ti ti-edit"></i> Manila Edit
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="messanger.php" class="dash-link">
                                    <i class="ti ti-brand-messenger"></i> Messenger
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="queries.php" class="dash-link">
                                    <i class="ti ti-code"></i> Queries
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="recover_missing_invoices.php" class="dash-link">
                                    <i class="ti ti-file-search"></i> Recover Invoices
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="recycle_bin.php" class="dash-link">
                                    <i class="ti ti-trash"></i> Recycle Bin
                                </a>
                            </li>
                            <!-- <li class="dash-item">
                                <a href="reminder_calender.php" class="dash-link">
                                    <i class="ti ti-calendar"></i> Reminder Calendar
                                </a>
                            </li> -->

                            <li class="dash-item">
                                <a href="sip.php" class="dash-link">
                                    <i class="ti ti-phone"></i> SIP
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="stripe_pay_link_gen.php" class="dash-link">
                                    <i class="ti ti-link"></i> Stripe Link Gen
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="stripe_payment_view.php" class="dash-link">
                                    <i class="ti ti-credit-card"></i> Stripe Payments
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!--------------------- See More End ----------------------------------->
                <?php } ?>

                <!--------------------- Active Inactive clients added ----------------------------------->

                <li class="dash-item dash-hasmenu" id="manage-clients-menu">

                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-users"></i></span>
                        <span class="dash-mtext">Manage Clients</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <?php if ($acc_add_client == "Yes") { ?>
                            <li class="dash-item">
                                <a href="add_client.php" class="dash-link">
                                    <i class=""></i> Add New
                                </a>
                            </li>
                        <?php } ?>
                        <?php if ($acc_view_client == "Yes") { ?>
                            <li class="dash-item">
                                <a class="dash-link" href="view_clients.php">
                                    <i class="ti ti-list"></i> View All Clients
                                </a>
                            </li>
                        <?php } ?>
                        <?php if ($acc_view_client == "Yes") { ?>
                            <li class="dash-item">
                                <a class="dash-link" href="view_clients.php?profile_status=1">
                                    <i class="ti ti-user-check"></i> Active Clients
                                </a>
                            </li>
                            <li class="dash-item">
                                <a class="dash-link" href="view_clients.php?profile_status=0">
                                    <i class="ti ti-user-x"></i> Inactive Clients
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>

                <!--------------------- Active Inactive clients end ----------------------------------->

               
                <!-- ========== COMMENTS GROUP ========== -->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-message-circle"></i></span>
                        <span class="dash-mtext">All Comments</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>

                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="comments.php" class="dash-link">
                                <i class="ti ti-list"></i>Comments
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="invoice_comments.php" class="dash-link">
                                <i class="ti ti-file-invoice"></i> Inv Comment
                            </a>
                        </li>

                        <li class="dash-item">
                            <a href="general_comments.php" class="dash-link">
                                <i class="ti ti-notes"></i> General
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="project_comments.php" class="dash-link">
                                <i class="ti ti-briefcase"></i> Project Comments
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="client_alerts.php" class="dash-link">
                                <i class="ti ti-bell"></i> Client Alerts
                            </a>
                        </li>
                       
                    </ul>
                </li>
                <!-- ========== /COMMENTS GROUP ========== -->

                <!-- ========== COMMUNICATION GROUP ========== -->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-brand-messenger"></i></span>
                        <span class="dash-mtext">Communication</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="comm_hub.php" class="dash-link">
                                <i class="ti ti-messages"></i> Communication
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="messanger.php" class="dash-link">
                                <i class="ti ti-brand-messenger"></i> Messenger
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- ========== /COMMUNICATION GROUP ========== -->

                <!--------------------- Projects added start ----------------------------------->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-briefcase"></i></span>
                        <span class="dash-mtext">Projects</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="add_new_projects.php" class="dash-link">
                                <i class="ti ti-plus"></i> Add New Project
                            </a>
                        </li>
                        <li class="dash-item">
                            <a class="dash-link" href="show_projects.php">
                                <i class="ti ti-list"></i> View All Projects
                            </a>
                        </li>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == '1') { ?>
                            <li class="dash-item">
                                <a class="dash-link" href="project_invoices.php">
                                    <i class="ti ti-file-invoice"></i> Project Invoices
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>

                <!--------------------- Renewals group ----------------------------------->

                <!--------------------- Projects added end ----------------------------------->

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="#" class="dash-link">
                            <span class="dash-micon"><i class="ti ti-align-box-center-middle-filled"></i></span>
                            <span class="dash-mtext">Invoice & Billing</span>
                            <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="dash-submenu">
                            <li class="dash-item">
                                <a href="invoice_billings.php" class="dash-link">
                                    <i class="ti ti-list"></i> All Invoices
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="instant_invoice.php" class="dash-link">
                                    <i class="ti ti-bolt"></i> Instant Invoice
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php } ?>

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="payment_plan.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-report-money"></i></span><span
                                class="dash-mtext">Payment Plan</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_view_client == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="#" class="dash-link">
                            <span class="dash-micon"><svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-lock-square-rounded-filled" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path
                                        d="M12 2c-.218 0 -.432 .002 -.642 .005l-.616 .017l-.299 .013l-.579 .034l-.553 .046c-4.785 .464 -6.732 2.411 -7.196 7.196l-.046 .553l-.034 .579c-.005 .098 -.01 .198 -.013 .299l-.017 .616l-.004 .318l-.001 .324c0 .218 .002 .432 .005 .642l.017 .616l.013 .299l.034 .579l.046 .553c.464 4.785 2.411 6.732 7.196 7.196l.553 .046l.579 .034c.098 .005 .198 .01 .299 .013l.616 .017l.642 .005l.642 -.005l.616 -.017l.299 -.013l.579 -.034l.553 -.046c4.785 -.464 6.732 -2.411 7.196 -7.196l.046 -.553l.034 -.579c.005 -.098 .01 -.198 .013 -.299l.017 -.616l.005 -.642l-.005 -.642l-.017 -.616l-.013 -.299l-.034 -.579l-.046 -.553c-.464 -4.785 -2.411 -6.732 -7.196 -7.196l-.553 -.046l-.579 -.034a28.058 28.058 0 0 0 -.299 -.013l-.616 -.017l-.318 -.004l-.324 -.001zm0 4a3 3 0 0 1 2.995 2.824l.005 .176v1a2 2 0 0 1 1.995 1.85l.005 .15v3a2 2 0 0 1 -1.85 1.995l-.15 .005h-6a2 2 0 0 1 -1.995 -1.85l-.005 -.15v-3a2 2 0 0 1 1.85 -1.995l.15 -.005v-1a3 3 0 0 1 3 -3zm3 6h-6v3h6v-3zm-3 -4a1 1 0 0 0 -.993 .883l-.007 .117v1h2v-1a1 1 0 0 0 -1 -1z"
                                        fill="currentColor" stroke-width="0"></path>
                                </svg></span><span class="dash-mtext">Client Types</span>
                            <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="dash-submenu">
                            <li class="dash-item">
                                <a class="dash-link" href="client_types.php">
                                    <i class="ti ti-list"></i> All Types
                                </a>
                            </li>
                            <?php if ($usertype == '1') { ?>
                                <li class="dash-item">
                                    <a class="dash-link" href="client_type_manual.php">
                                        <i class="ti ti-settings"></i> Type Manual
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </li>
                <?php } ?>

                <?php if ($acc_view_products == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="memberships.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-shopping-cart"></i></span><span class="dash-mtext">View
                                Services</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_view_staff == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="users.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-user-plus"></i></span><span class="dash-mtext">Manage
                                Staff</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_view_entity == "Yes") { ?>
                    <!-- <li class="dash-item dash-hasmenu">
                        <a href="entity_form.php" class="dash-link ">
                            <span class="dash-micon"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-article-filled-filled" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M19 3a3 3 0 0 1 2.995 2.824l.005 .176v12a3 3 0 0 1 -2.824 2.995l-.176 .005h-14a3 3 0 0 1 -2.995 -2.824l-.005 -.176v-12a3 3 0 0 1 2.824 -2.995l.176 -.005h14zm-2 12h-10l-.117 .007a1 1 0 0 0 0 1.986l.117 .007h10l.117 -.007a1 1 0 0 0 0 -1.986l-.117 -.007zm0 -4h-10l-.117 .007a1 1 0 0 0 0 1.986l.117 .007h10l.117 -.007a1 1 0 0 0 0 -1.986l-.117 -.007zm0 -4h-10l-.117 .007a1 1 0 0 0 0 1.986l.117 .007h10l.117 -.007a1 1 0 0 0 0 -1.986l-.117 -.007z" stroke-width="0" fill="currentColor"></path>
                            </svg></span><span class="dash-mtext">Entity Form</span>
                        </a>
                </li> -->
                <?php } ?>


                <?php if ($acc_view_product == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="reminder_calender.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-calendar"></i></span>
                            <span class="dash-mtext">Reminders</span>
                        </a>
                    </li><?php } ?>

                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-refresh"></i></span>
                        <span class="dash-mtext">Product Renewals</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="ToBeRenewed.php" class="dash-link">
                                <i class="ti ti-clock"></i> To Be Renewed
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="recently_renewed.php" class="dash-link">
                                <i class="ti ti-check"></i> Recently Renewed
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="expired_renewal.php" class="dash-link">
                                <i class="ti ti-alert-circle"></i> Expired Renewals
                            </a>
                        </li>
                    </ul>
                </li>

                <?php if ($acc_estimates_and_qutations == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="quotations.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-file-invoice"></i></span><span
                                class="dash-mtext">Estimate/Quotes</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="manage_roles.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-user-shield"></i></span><span
                                class="dash-mtext">Roles &amp; Permissions</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="customizer.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-adjustments-horizontal"></i></span><span
                                class="dash-mtext">Customizer</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($aiShowChat) { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="#" class="dash-link" onclick="if(window.aiToggle){event.preventDefault();aiToggle(true);}">
                            <span class="dash-micon"><i class="ti ti-message-circle-bolt"></i></span><span
                                class="dash-mtext">AI Assistant</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="cursor_settings.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-settings"></i></span><span
                                class="dash-mtext">Settings</span>
                        </a>
                    </li>
                    <li class="dash-item dash-hasmenu">
                        <a href="ai_settings.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-robot"></i></span><span
                                class="dash-mtext">AI Settings</span>
                        </a>
                    </li>
                <?php } ?>
 <!--------------------- Additional Menu ----------------------------------->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-apps"></i></span>
                        <span class="dash-mtext">Additional</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="stripe_pay_link_gen.php" class="dash-link">
                                <i class="ti ti-link"></i> Stripe Link
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="bulk_monthly_payer_gen.php" class="dash-link">
                                <i class="ti ti-calendar-dollar"></i> Bulk inv
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="ach_payment_collector.php" class="dash-link">
                                <i class="ti ti-building-bank"></i> ACH Collector
                            </a>
                        </li>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                            <li class="dash-item">
                                <a href="bulk_email.php" class="dash-link">
                                    <i class="ti ti-mail-forward"></i> Bulk Client Email
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
                <!--------------------- Additional Menu End ----------------------------------->

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="report.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-file-invoice"></i></span><span
                                class="dash-mtext">Report</span>
                        </a>
                    </li>
                <?php } ?>

               


            </ul>

        </div>
    </div>
</nav>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname.split('/').pop();

        const activeLink = document.querySelector(`.dash-link[href="${currentPath}"]`);

        if (activeLink) {
            activeLink.classList.add('active');
            let parent = activeLink.closest('.dash-item.dash-hasmenu');
            if (parent) parent.classList.add('active');
        }

        // ===== See More password protection =====
        const SEE_MORE_AUTH_KEY = 'see_more_authenticated';
        const SEE_MORE_PASSWORD = '@skipper@';

        function isSeeMoreAuthenticated() {
            return sessionStorage.getItem(SEE_MORE_AUTH_KEY) === 'true';
        }

        function promptSeeMorePassword() {
            if (typeof Swal === 'undefined') {
                const pwd = window.prompt('Enter password to access See More:');
                if (pwd === SEE_MORE_PASSWORD) {
                    sessionStorage.setItem(SEE_MORE_AUTH_KEY, 'true');
                    return Promise.resolve(true);
                }
                if (pwd !== null) window.alert('Incorrect password');
                return Promise.resolve(false);
            }
            return Swal.fire({
                title: 'Password Required',
                text: 'Enter password to access See More',
                input: 'password',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off',
                    autocomplete: 'off',
                    placeholder: 'Password'
                },
                showCancelButton: true,
                confirmButtonText: 'Unlock',
                confirmButtonColor: '#252161',
                preConfirm: (password) => {
                    if (password !== SEE_MORE_PASSWORD) {
                        Swal.showValidationMessage('Incorrect password');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    sessionStorage.setItem(SEE_MORE_AUTH_KEY, 'true');
                    return true;
                }
                return false;
            });
        }

        const seeMoreTrigger = document.getElementById('see-more-trigger');
        const seeMoreMenu = document.getElementById('see-more-menu');

        if (seeMoreTrigger) {
            seeMoreTrigger.addEventListener('click', function(e) {
                if (isSeeMoreAuthenticated()) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                promptSeeMorePassword().then((unlocked) => {
                    if (!unlocked) return;
                    const parent = seeMoreTrigger.parentElement;
                    if (!document.body.classList.contains('sidebar-collapsed')) {
                        document.querySelectorAll('.dash-item.dash-hasmenu').forEach(item => {
                            if (item !== parent) item.classList.remove('active');
                        });
                        parent.classList.add('active');
                    }
                });
            }, true); // capture phase: runs before the generic toggle handler below
        }

        if (seeMoreMenu) {
            // Block the collapsed-sidebar hover popover from leaking submenu items
            seeMoreMenu.addEventListener('mouseenter', function(e) {
                if (!document.body.classList.contains('sidebar-collapsed')) return;
                if (isSeeMoreAuthenticated()) return;
                e.stopImmediatePropagation();
            }, true);
        }
        // ===== /See More password protection =====

        const menuToggles = document.querySelectorAll('.dash-item.dash-hasmenu > .dash-link');
        menuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                const parent = this.parentElement;
                const submenu = parent.querySelector('.dash-submenu');

                if (submenu) {
                    e.preventDefault();
                    if (!document.body.classList.contains('sidebar-collapsed')) {
                        document.querySelectorAll('.dash-item.dash-hasmenu').forEach(item => {
                            if (item !== parent) item.classList.remove('active');
                        });
                        parent.classList.toggle('active');
                    }
                }
            });
        });

        const toggleBtn = document.getElementById('sidebar-toggle');
        const toggleIcon = document.getElementById('toggle-icon');
        const body = document.body;
        const SIDEBAR_STATE_KEY = 'sidebar_collapsed_state';

        function setSidebarState(collapsed) {
            if (collapsed) {
                body.classList.add('sidebar-collapsed');
                if (toggleIcon) toggleIcon.classList.replace('ti-chevron-left', 'ti-chevron-right');
            } else {
                body.classList.remove('sidebar-collapsed');
                if (toggleIcon) toggleIcon.classList.replace('ti-chevron-right', 'ti-chevron-left');
            }
        }

        const savedState = localStorage.getItem(SIDEBAR_STATE_KEY);
        if (savedState === 'true') {
            setSidebarState(true);
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isCollapsed = !body.classList.contains('sidebar-collapsed');
                setSidebarState(isCollapsed);
                localStorage.setItem(SIDEBAR_STATE_KEY, isCollapsed);
            });
        }

        let activePopover = null;
        let popoverTimeout = null;

        const dashItems = document.querySelectorAll('.dash-item');
        dashItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (!body.classList.contains('sidebar-collapsed')) return;

                if (popoverTimeout) {
                    clearTimeout(popoverTimeout);
                    popoverTimeout = null;
                }

                if (activePopover && activePopover.dataset.targetId === item.innerHTML) {
                    return;
                }

                if (activePopover) {
                    activePopover.remove();
                    activePopover = null;
                }

                const rect = item.getBoundingClientRect();
                const submenu = item.querySelector('.dash-submenu');

                const mtext = item.querySelector('.dash-mtext');
                const linkText = mtext ? mtext.textContent.trim() : '';

                const popover = document.createElement('div');
                popover.className = 'sidebar-popover';
                popover.style.top = (rect.top - 5) + 'px';
                popover.style.display = 'block';
                popover.dataset.targetId = item.innerHTML;

                if (submenu) {
                    const header = document.createElement('div');
                    header.className = 'popover-header';
                    header.textContent = linkText;
                    popover.appendChild(header);

                    const links = submenu.querySelectorAll('.dash-link');
                    links.forEach(l => {
                        const clonedLink = l.cloneNode(true);
                        clonedLink.removeAttribute('style');
                        clonedLink.classList.remove('active');

                        const icons = clonedLink.querySelectorAll('i, span, svg');
                        icons.forEach(i => i.style.display = 'inline-block');

                        clonedLink.addEventListener('click', function(e) {
                            if (l.getAttribute('href') !== '#' && !l.getAttribute('onclick')) {
                                return;
                            }

                            e.preventDefault();
                            l.click();
                        });

                        popover.appendChild(clonedLink);
                    });
                } else {
                    popover.innerHTML = `<div class="popover-header" style="border:none; padding-bottom:12px; border-radius:4px;">${linkText}</div>`;
                }

                document.body.appendChild(popover);
                activePopover = popover;


                popover.addEventListener('mouseenter', function() {
                    if (popoverTimeout) clearTimeout(popoverTimeout);
                });


                popover.addEventListener('mouseleave', function() {
                    popoverTimeout = setTimeout(() => {
                        if (activePopover) {
                            activePopover.remove();
                            activePopover = null;
                        }
                    }, 150);
                });
            });


            item.addEventListener('mouseleave', function(e) {
                if (!body.classList.contains('sidebar-collapsed')) return;

                popoverTimeout = setTimeout(() => {
                    if (activePopover) {
                        activePopover.remove();
                        activePopover = null;
                    }
                }, 150);
            });
        });

        window.addEventListener('scroll', function() {
            if (activePopover) {
                activePopover.remove();
                activePopover = null;
            }
        }, true);
    });

    const sidebar = document.querySelector('.dash-sidebar');
    if (sidebar) {
        sidebar.style.overflowY = 'auto';
    }
</script>