<style type="text/css">
    /* ============================================================
       SPS Sidebar - structural styles only.
       Theme colours / shells are driven by theme.php tokens
       (body.sps-root .sps-p-*). Fallbacks below keep pages usable
       even when the dynamic theme is switched off.
       ============================================================ */

    /* submenu expansion is controlled by the .active class */
    .dash-submenu {
        display: none;
    }

    .dash-item.active .dash-submenu {
        display: block;
    }

    /* flex column: header + logo + scrollable nav region */
    .dash-sidebar {
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        -webkit-box-orient: vertical;
        -webkit-box-direction: normal;
        -ms-flex-direction: column;
        flex-direction: column;
    }

    .navbar-wrapper {
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        -webkit-box-orient: vertical;
        -webkit-box-direction: normal;
        -ms-flex-direction: column;
        flex-direction: column;
        -webkit-box-flex: 1;
        -ms-flex: 1;
        flex: 1;
        min-height: 0;
    }

    .navbar-content {
        -webkit-box-flex: 1;
        -ms-flex: 1;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .navbar-content::-webkit-scrollbar {
        width: 0px;
        background: transparent;
    }

    /* logo lockup (theme.php scales it down further) */
    .dash-sidebar .main-logo {
        justify-content: center;
        min-height: 100px;
        max-height: 112px;
        min-width: 236px;
    }

    .dash-sidebar .main-logo a img {
        width: fit-content;
        height: 60px;
        object-fit: contain;
        max-width: -webkit-fill-available;
        max-width: -moz-available;
        max-height: -webkit-fill-available;
        max-height: -moz-available;
        margin: 0 auto;
        display: block;
    }

    /* group captions */
    .dash-caption {
        padding: 16px 12px 6px;
        font-size: 10.5px;
        line-height: 1.2;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        white-space: nowrap;
        color: var(--sps-sidebar-muted, #8a8fa5);
    }

    .dash-navbar>.dash-caption:first-child {
        padding-top: 6px;
    }

    /* hide the template perfect-scrollbar thumb */
    .ps__thumb-y {
        display: none;
    }

    /* ---- generic tooltip (used across many pages) ---- */
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

    /* ==========================================
       Collapsed icon rail (structural)
       ========================================== */

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
    body.sidebar-collapsed .dash-sidebar .badge,
    body.sidebar-collapsed .dash-caption {
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

    body.sidebar-collapsed .dash-sidebar .dash-navbar>.dash-item>.dash-link {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ==========================================
       Default (non-active) items: subtle accent
       tint so links read as chips. Active states
       are fully token-driven by theme.php.
       ========================================== */

    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link {
        background: rgba(var(--sps-accent-rgb, 37, 33, 97), .07);
        color: var(--sps-sidebar-text, #252161);
    }

    /* fix: long labels ("Product Renewals") left no room for the
       chevron on the same line. Lay the link out as a flex row so the
       arrow always stays pinned to the end of its own row. */
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

    /* ==========================================
       Collapsed hover popover (tokenised)
       ========================================== */

    .sidebar-popover {
        position: fixed;
        left: 80px;
        background: var(--sps-surface, #fff);
        border: 1px solid var(--sps-border, #e1e1e1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        z-index: 9999 !important;
        border-radius: 10px;
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
        background: var(--sps-surface, #fff);
        transform: rotate(45deg);
        border-left: 1px solid var(--sps-border, #e1e1e1);
        border-bottom: 1px solid var(--sps-border, #e1e1e1);
    }

    .sidebar-popover .dash-link {
        padding: 11px 18px;
        display: flex;
        align-items: center;
        color: var(--sps-text, #333) !important;
        text-decoration: none;
        text-align: left;
        justify-content: flex-start;
        transition: background 0.2s;
        font-size: 13.5px;
        border-bottom: 1px solid var(--sps-border, #f0f0f0);
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
        color: var(--sps-muted, #555);
        width: 20px;
        text-align: center;
    }

    .sidebar-popover .dash-link:hover {
        background: var(--sps-soft, #f8f9fa);
        color: var(--sps-accent, #007bff) !important;
    }

    .sidebar-popover .dash-link:hover i {
        color: var(--sps-accent, #007bff);
    }

    .sidebar-popover .popover-header {
        font-weight: 700;
        padding: 11px 18px;
        border-bottom: 1px solid var(--sps-border, #eee);
        background: var(--sps-surface2, #f1f1f1);
        color: var(--sps-heading, #000) !important;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dash-sidebar,
    .dash-container,
    .dash-micon {
        transition: all 0.3s ease;
    }

    /* ---- See More lock trigger ---- */
    #see-more-trigger {
        color: var(--sps-accent, #f5a623) !important;
        font-weight: 600;
    }

    #see-more-trigger .dash-micon i,
    #see-more-trigger .dash-arrow i {
        color: var(--sps-accent, #f5a623) !important;
    }

    #see-more-trigger:hover {
        background: rgba(var(--sps-accent-rgb, 245, 166, 35), 0.12) !important;
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
    /* ---- SPS Assistant popup modal ---- */
    #spsFab {
        position: fixed; right: 22px; bottom: 22px; z-index: 998;
        width: 58px; height: 58px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, #51459d, #1456b8 70%, #3b82f6); color: #fff;
        box-shadow: 0 8px 22px rgba(81, 69, 157, .45); display: flex; align-items: center; justify-content: center;
        font-size: 25px; transition: transform .15s;
    }
    #spsFab:hover { transform: scale(1.08); }
    #spsModal {
        position: fixed; right: 20px; bottom: 92px; width: 440px; max-width: calc(100vw - 40px);
        height: 640px; max-height: calc(100vh - 120px); z-index: 999;
        background: #fff; border-radius: 18px; box-shadow: 0 22px 60px rgba(15, 23, 42, .35);
        display: none; flex-direction: column; overflow: hidden; border: 1px solid #e8ecf1;
    }
    #spsModal.open { display: flex; }
    #spsModal.min { height: 56px !important; min-height: 56px !important; }
    #spsHead {
        background: linear-gradient(120deg, #0f172a 0%, #51459d 60%, #1456b8 100%);
        color: #fff; padding: 12px 16px; flex: 0 0 auto;
    }
    #spsHeadT { display: flex; align-items: center; gap: 10px; }
    #spsHeadT b { flex: 1; font-size: 14px; letter-spacing: .02em; }
    #spsHeadT .ico { font-size: 19px; }
    #spsHeadBtns { display: flex; gap: 4px; }
    #spsHeadBtns button { background: rgba(255,255,255,.14); border: none; color: #fff; width: 26px; height: 26px; border-radius: 7px; cursor: pointer; font-size: 14px; line-height: 1; }
    #spsHeadBtns button:hover { background: rgba(255,255,255,.28); }
    #spsStatus { display: flex; align-items: center; gap: 8px; margin-top: 9px; background: rgba(255,255,255,.12); border-radius: 10px; padding: 6px 9px; font-size: 11.5px; }
    #spsDot { width: 9px; height: 9px; border-radius: 50%; background: #fca02b; flex: 0 0 auto; animation: spsPulse 1.6s infinite; }
    #spsDot.online { background: #34d399; }
    #spsDot.offline { background: #fca02b; }
    #spsDot.dead { background: #f87171; animation: none; }
    #spsStatLabel { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    @keyframes spsPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(252,160,43,.6); } 50% { box-shadow: 0 0 0 5px rgba(252,160,43,0); } }
    .sps-switch { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; }
    .sps-switch input { display: none; }
    .sps-switch .k { width: 34px; height: 19px; border-radius: 12px; background: #fca02b; position: relative; cursor: pointer; transition: .25s; }
    .sps-switch input:checked + .k { background: #34d399; }
    .sps-switch .k:after { content: ''; position: absolute; top: 2.5px; left: 3px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: .25s; }
    .sps-switch input:checked + .k:after { transform: translateX(14px); }
    /* mode tabs */
    #spsModes { display: flex; gap: 6px; padding: 9px 12px 0; background: #fff; border-bottom: 1px solid #eef1f6; flex: 0 0 auto; }
    #spsModes button {
        flex: 1; border: none; background: transparent; color: #64748b; font-weight: 600; font-size: 12px;
        padding: 8px 4px 10px; cursor: pointer; border-bottom: 2px solid transparent; display: flex; align-items: center; justify-content: center; gap: 5px;
    }
    #spsModes button i { font-size: 14px; }
    #spsModes button.on { color: #51459d; border-bottom-color: #51459d; }
    /* chat body */
    #spsBody { flex: 1; padding: 12px; overflow-y: auto; background: #f7f9fc; }
    .ai-msg { margin-bottom: 12px; display: flex; }
    .ai-msg.bot { justify-content: flex-start; }
    .ai-msg.user { justify-content: flex-end; }
    .ai-msg .who { display: block; font-size: 10px; color: #94a3b8; margin-bottom: 3px; margin-left: 2px; }
    .ai-bubble { max-width: 90%; padding: 9px 12px; border-radius: 12px; font-size: 13px; white-space: pre-wrap; word-break: break-word; }
    .ai-msg.bot .ai-bubble { background: #fff; border: 1px solid #e8ecf1; color: #1e293b; border-top-left-radius: 3px; }
    .ai-msg.user .ai-bubble { background: linear-gradient(135deg, #51459d, #1456b8); color: #fff; border-top-right-radius: 3px; }
    .ai-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; background: #fff; }
    .ai-table th, .ai-table td { border: 1px solid #dde3ec; padding: 5px 6px; text-align: left; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ai-table th { background: #eef2f7; }
    .ai-tools { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
    .ai-tools button, .ai-confirmbutton { border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; cursor: pointer; color: #fff; }
    .ai-csv { background: #16a34a; } .ai-pdf { background: #dc2626; } .ai-confirm { background: #1456b8; } .ai-cancel { background: #64748b !important; }
    .ai-preview { background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; margin-top: 8px; }
    .ai-preview h6 { margin: 0 0 8px; color: #51459d; font-size: 13px; }
    .ai-preview table { width: 100%; font-size: 12px; border-collapse: collapse; }
    .ai-preview td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; font-size: 12px; }
    .ai-preview td b { color: #334155; }
    .ai-confirmwrap { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
    .ai-preview .ed { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 3px 7px; font-size: 12px; background: #fbfcfe; }
    #spsFoot { padding: 10px; border-top: 1px solid #e8ecf1; background: #fff; flex: 0 0 auto; }
    #spsImgName { font-size: 11px; color: #64748b; margin: 0 2px 6px; }
    #spsRow { display: flex; align-items: flex-end; gap: 8px; }
    #spsIn { flex: 1; border: 1px solid #cbd5e1; border-radius: 12px; padding: 9px 11px; font-size: 13px; resize: none; max-height: 96px; outline: none; }
    #spsIn:focus { border-color: #51459d; }
    .sp-icobtn { background: none; border: none; font-size: 22px; cursor: pointer; color: #51459d; line-height: 1; padding: 3px; }
    .sp-icobtn.rec { color: #dc2626; }
    .sp-mini { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
    .sp-mini button { border: 1px solid #dbe2ea; background: #fff; color: #334155; border-radius: 18px; padding: 4px 11px; font-size: 11px; cursor: pointer; }
    .sp-mini button:hover { background: #eef0fb; color: #51459d; }
    .sps-mode-tag { display: inline-block; background: #eef0fb; color: #51459d; border-radius: 6px; padding: 1px 8px; font-size: 10px; font-weight: 700; margin-right: 6px; }
    @media (max-width: 560px) { #spsModal { right: 8px; left: 8px; width: auto; max-width: none; } }
</style>

<div id="spsFab" title="SPS Assistant"><i class="ti ti-robot"></i></div>

<div id="spsModal">
    <div id="spsHead">
        <div id="spsHeadT">
            <i class="ti ti-robot ico"></i>
            <b>SPS Assistant</b>
            <div id="spsHeadBtns">
                <button type="button" title="Minimize" onclick="spsMinimize()"><i class="ti ti-minus"></i></button>
                <button type="button" title="Open full page" onclick="window.open('ai.php','_blank')"><i class="ti ti-external-link"></i></button>
                <button type="button" title="Close" onclick="spsClose()"><i class="ti ti-x"></i></button>
            </div>
        </div>
        <div id="spsStatus">
            <span id="spsDot"></span>
            <span id="spsStatLabel">Checking status...</span>
            <label class="sps-switch" title="Toggle Online (Gemini) / Offline (local AI)">
                <span>Online</span>
                <input type="checkbox" id="spsModeTgl"><span class="k"></span>
                <span>Local</span>
            </label>
        </div>
    </div>
    <div id="spsModes">
        <button type="button" data-mode="insert" class="on"><i class="ti ti-square-plus"></i> Insert</button>
        <button type="button" data-mode="search"><i class="ti ti-search"></i> Search</button>
        <button type="button" data-mode="talk"><i class="ti ti-microphone"></i> Talk</button>
        <button type="button" data-mode="report"><i class="ti ti-report"></i> Report</button>
    </div>
    <div id="spsBody">
        <div id="spsMsgs">
            <div class="ai-msg bot"><div class="ai-bubble">Hello! I'm SPS Assistant. I can help you <b>insert</b> new records (clients, invoices, projects, estimates), <b>search</b> your CRM (with exact &amp; similar matches), <b>talk</b> to you by voice, and build <b>reports</b> (CSV / PDF / Excel). Choose a mode above or just type.</div></div>
            <div class="sp-mini" id="spsQuick">
                <button type="button" data-q="Add a new client named John Smith in New York">Insert a client</button>
                <button type="button" data-q="Search for client atif@syedpro.com">Search email</button>
                <button type="button" data-q="List 5 active clients with phone and email">List clients</button>
                <button type="button" data-q="Generate a CSV report of all sales tax clients for 2025">Report sales-tax 2025</button>
            </div>
        </div>
    </div>
    <div id="spsFoot">
        <div id="spsImgName"></div>
        <div id="spsRow">
            <button class="sp-icobtn" id="spsMic" title="Voice talk" onclick="spsVoice()"><i class="ti ti-microphone"></i></button>
            <label class="sp-icobtn" title="Upload file (image / PDF / doc / excel / csv)"><i class="ti ti-upload"></i><input type="file" id="spsFile" accept="image/*,.pdf,.txt,.csv,.xls,.xlsx,.doc,.docx,.log,.md" style="display:none" onchange="spsUpload(this)"></label>
            <textarea id="spsIn" rows="1" placeholder="Insert, search, talk or report…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();spsSend();}"></textarea>
            <button class="sp-icobtn" id="spsSendBtn" title="Send" onclick="spsSend()"><i class="ti ti-send"></i></button>
        </div>
    </div>
</div>

<script>
    (function () {
        window.spsState = { pending: null, mode: 'insert', sid: 0, speak: false };
        window.__spsLatestSql = '';
        window.__spsSpeakOn = true;

        /* ---- modal open/close + persistence across pages ---- */
        function spsApplyOpen() {
            var m = document.getElementById('spsModal');
            var open = localStorage.getItem('spsAssistOpen') === '1';
            m.classList.toggle('open', open);
        }
        window.spsOpen = function () {
            localStorage.setItem('spsAssistOpen', '1');
            document.getElementById('spsModal').classList.add('open');
            document.getElementById('spsModal').classList.remove('min');
            setTimeout(function () { var i = document.getElementById('spsIn'); if (i) i.focus(); }, 60);
            spsCheckMode();
        };
        window.spsClose = function () {
            localStorage.setItem('spsAssistOpen', '0');
            document.getElementById('spsModal').classList.remove('open');
            if (window.speechSynthesis) speechSynthesis.cancel();
        };
        window.spsMinimize = function () {
            var m = document.getElementById('spsModal');
            m.classList.toggle('min');
        };
        document.getElementById('spsFab').addEventListener('click', function () { spsOpen(); });

        /* ---- mode tabs ---- */
        window.spsSetMode = function (mode) {
            spsState.mode = mode;
            document.querySelectorAll('#spsModes button').forEach(function (b) { b.classList.toggle('on', b.dataset.mode === mode); });
            var ph = {
                insert: 'Describe what to add: "Add a client named…", "Create invoice for client #5…"',
                search: 'Search: "client atif@syedpro.com", "projects of John", "invoices for 2025"…',
                talk: 'Speak or type: "open client #8484", "open projects of John Smith"…',
                report: 'Report: "create CSV of sales tax clients of 2025", "total revenue by client type"…'
            };
            document.getElementById('spsIn').setAttribute('placeholder', ph[mode] || '');
        };
        document.querySelectorAll('#spsModes button').forEach(function (b) {
            b.addEventListener('click', function () { spsSetMode(b.dataset.mode); });
        });
        spsSetMode('insert');

        /* ---- online/offline status + toggle ---- */
        window.spsCheckMode = function () {
            var fd = new FormData(); fd.append('action', 'check_mode');
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok || !res.mode) return;
                    var st = res.mode;
                    var dot = document.getElementById('spsDot');
                    var lbl = document.getElementById('spsStatLabel');
                    var tgl = document.getElementById('spsModeTgl');
                    tgl.checked = (st.current === 'offline');
                    if (!st.gemini_ok && !st.groq_ok && !st.ollama_ok) {
                        dot.className = 'dead'; lbl.textContent = 'No AI backend reachable (check settings / Ollama)';
                    } else if (st.current === 'offline') {
                        dot.className = 'offline'; lbl.textContent = 'Offline mode · Local AI (' + (st.ollama_model || 'model') + ')';
                        spsAddBot('<span class="sps-mode-tag">LOCAL</span> Running on the local AI (no internet needed). Toggle Online to use Gemini.');
                    } else {
                        dot.className = 'online'; lbl.textContent = (st.provider === 'groq') ? 'Online mode · Groq (Gemini down)' : 'Online mode · Gemini AI';
                    }
                })
                .catch(function () { document.getElementById('spsDot').className = 'dead'; document.getElementById('spsStatLabel').textContent = 'Status unavailable'; });
        };
        document.getElementById('spsModeTgl').addEventListener('change', function () {
            var wantOffline = this.checked;
            var fd = new FormData();
            fd.append('action', 'switch_mode');
            fd.append('mode', wantOffline ? 'offline' : 'online');
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('<b>Mode change failed:</b> ' + (res.error || 'unknown')); spsCheckMode(); return; }
                    spsAddBot(wantOffline ? '<span class="sps-mode-tag">LOCAL</span> Switched to <b>offline local AI</b>. No internet needed for answers.' : 'Switched to <b>online Gemini</b>.');
                    spsCheckMode();
                })
                .catch(function () { this.checked = !wantOffline; spsAddBot('Could not switch mode (network error).'); });
        });

        /* ---- chat plumbing ---- */
        function spsAddBot(html, speak) {
            var wrap = document.createElement('div');
            wrap.className = 'ai-msg bot';
            wrap.innerHTML = '<span class="who">SPS Assistant</span><div class="ai-bubble">' + html + '</div>';
            document.getElementById('spsMsgs').appendChild(wrap);
            spsScrollDown();
            if (speak !== false && window.__spsSpeakOn) spsSpeak(stripHtml(html));
            return wrap;
        }
        function spsAddUser(txt) {
            var wrap = document.createElement('div');
            wrap.className = 'ai-msg user';
            wrap.innerHTML = '<span class="who">You</span><div class="ai-bubble">' + String(txt).replace(/</g, '&lt;') + '</div>';
            document.getElementById('spsMsgs').appendChild(wrap);
            spsScrollDown();
        }
        function spsScrollDown() { var b = document.getElementById('spsBody'); b.scrollTop = b.scrollHeight; }
        function stripHtml(h) { return String(h).replace(/<[^>]+>/g, ''); }
        function spsSpeak(text) {
            if (!('speechSynthesis' in window)) return;
            speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(text);
            u.rate = 1.02; u.pitch = 1;
            speechSynthesis.speak(u);
        }
        document.getElementById('spsSendBtn').addEventListener('click', function () { spsSend(); });
        var qbtns = document.getElementById('spsQuick');
        if (qbtns) qbtns.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b || !b.dataset.q) return;
            var m = b.dataset.mode;
            if (m) spsSetMode(m);
            document.getElementById('spsIn').value = b.dataset.q; spsSend();
        });

        /* ---- send ---- */
        function spsSend() {
            var inp = document.getElementById('spsIn');
            var msg = inp.value.trim();
            if (!msg) return;
            spsAddUser(msg);
            inp.value = '';
            spsAddBot('<i>Thinking…</i>', false);
            var fd = new FormData();
            fd.append('action', 'chat');
            fd.append('message', msg);
            fd.append('mode', spsState.mode);
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    spsRemoveThinking();
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || 'Unknown')); return; }
                    spsHandle(res);
                })
                .catch(function () { spsRemoveThinking(); spsAddBot('Network error — please try again.'); });
        }
        window.spsSend = spsSend;
        function spsRemoveThinking() {
            var body = document.getElementById('spsMsgs');
            var last = body.lastElementChild;
            if (last && last.textContent.indexOf('Thinking') !== -1) last.remove();
        }

        /* ---- response dispatcher ---- */
        function spsHandle(res) {
            s = res.session_id && (spsState.sid = res.session_id);
            switch (res.type) {
                case 'newclient': spsAddBot(res.text); spsShowClientPreview(res.fields); break;
                case 'newinvoice': spsAddBot(res.text); spsShowInvoicePreview(res.fields); break;
                case 'newinstant': spsAddBot(res.text); spsShowInstantPreview(res.fields); break;
                case 'newproject': spsAddBot(res.text); spsShowProjectPreview(res.fields); break;
                case 'newestimate': spsAddBot(res.text); spsShowEstimatePreview(res.fields); break;
                case 'newplan': spsAddBot(res.text); spsShowPlanPreview(res.fields); break;
                case 'note': spsAddBot(res.text); spsShowNotePreview(res.member_id, res.text_note); break;
                case 'file_attach': spsAddBot(res.text); spsShowAttach(res.member_id, res.file_hint, res.notes); break;
                case 'table':
                    spsAddBot((res.text || '').replace(/</g, '&lt;') + '<br>' + spsTable(res.rows, res.sql));
                    break;
                case 'open': spsOpenTarget(res.target); spsAddBot(res.text); break;
                case 'ocr':
                    spsAddBot((res.summary || 'OCR result').replace(/</g, '&lt;'));
                    if (res.fields && Object.keys(res.fields).length) {
                        var pt = res.preview_type || 'newclient';
                        if (pt === 'newinvoice') spsShowInvoicePreview(res.fields);
                        else if (pt === 'newinstant') spsShowInstantPreview(res.fields);
                        else if (pt === 'newestimate') spsShowEstimatePreview(res.fields);
                        else if (pt === 'newproject') spsShowProjectPreview(res.fields);
                        else spsShowClientPreview(res.fields, true);
                    }
                    break;
                default: spsAddBot((res.text || 'Done.').replace(/</g, '&lt;'));
            }
        }
        function spsTable(rows, sql) {
            if (!rows || !rows.length) return '<div style="font-size:12px;color:#64748b">No matching records.</div>';
            var h = Object.keys(rows[0]);
            var html = '<div style="overflow:auto;max-height:240px"><table class="ai-table"><thead><tr>';
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
            window.__spsLatestSql = sql;
            html += '<div class="ai-tools">';
            html += '<button class="ai-csv" onclick="spsExport(\'csv\')">CSV</button>';
            html += '<button class="ai-pdf" onclick="spsExport(\'pdf\')">PDF</button>';
            html += '<button class="ai-csv" onclick="spsExport(\'xlsx\')">Excel</button>';
            html += '<button class="ai-pdf" onclick="spsExport(\'docx\')">Word</button>';
            html += '</div>';
            return html;
        }
        window.spsExport = function (fmt) {
            var act = { 'csv': 'export_csv', 'pdf': 'export_pdf', 'xlsx': 'export_xlsx', 'docx': 'export_docx' }[fmt] || 'export_csv';
            var fd = new FormData(); fd.append('action', act); fd.append('sql', window.__spsLatestSql || '');
            if (fmt === 'pdf' || fmt === 'docx') fd.append('title', 'SPS Assistant Export');
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) {
                    var ct = r.headers.get('Content-Type') || '';
                    if (ct.indexOf('application/json') !== -1) return r.json().then(function (j) { spsAddBot('Error: ' + (j.error || 'Unknown')); return null; });
                    return r.blob();
                })
                .then(function (b) {
                    if (!b) return;
                    var url = URL.createObjectURL(b);
                    var a = document.createElement('a'); a.href = url; a.download = 'sps_export.' + fmt;
                    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                });
        };
        function spsOpenTarget(t) {
            var map = { clients: 'view_clients.php', invoices: 'invoice_billings.php', projects: 'projects.php', services: 'memberships.php', payments: 'invoice_billings.php', calendar: 'reminder_calender.php', settings: 'ai_settings.php', estimates: 'quotations.php', plans: 'payment_plan.php', dashboard: 'index.php', index: 'index.php' };
            var num = parseInt(t, 10);
            if (num > 0) { window.open('client_details.php?id=' + num, '_blank'); return; }
            var key = (t || '').trim().toLowerCase();
            var url = map[key] || 'index.php';
            window.open(url, '_blank');
        }

        /* ---- preview: client ---- */
        function spsShowClientPreview(fields, editable) {
            var f = fields || {}; spsState.pending = { type: 'client', fields: f };
            var inp = function (k, v) { return editable ? '<input class="ed" id="spsclient_' + k + '" value="' + String(v == null ? '' : v).replace(/"/g, '&quot;') + '">' : String(v == null ? '' : v).replace(/</g, '&lt;'); };
            var html = '<div class="ai-preview"><h6>New client — verify before inserting</h6><table>';
            [['name', 'First name'], ['lname', 'Last name'], ['bus_name', 'Business'], ['phone', 'Phone'], ['cell_ph', 'Cell'], ['prm_email', 'Email'], ['address', 'Address'], ['city', 'City'], ['state', 'State'], ['zip', 'Zip'], ['ss', 'SSN/TIN'], ['ein', 'EIN']].forEach(function (r) {
                html += '<tr><td><b>' + r[1] + '</b></td><td>' + inp(r[0], f[r[0]] || '') + '</td></tr>';
            });
            html += '</table><div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmClient()">Confirm &amp; Insert</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
            if (editable) spsAddBot('You can edit any field above, then click <b>Confirm &amp; Insert</b>.', false);
        }
        window.spsConfirmClient = function () {
            var p = spsState.pending; if (!p) return;
            var fields = {};
            Object.keys(p.fields).forEach(function (k) { fields[k] = p.fields[k]; });
            ['name','lname','bus_name','phone','cell_ph','prm_email','address','city','state','zip','ss','ein'].forEach(function (k) {
                var el = document.getElementById('spsclient_' + k); if (el) fields[k] = el.value;
            });
            var fd = new FormData(); fd.append('action', 'preview_client'); fd.append('fields', JSON.stringify(fields));
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_client'); f2.append('confirmed', '1'); f2.append('fields', JSON.stringify(res.preview));
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) {
                    if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '') + '<br><a href="client_details.php?id=' + res.member_id + '" target="_blank" style="color:#1456b8">Open client #' + res.member_id + ' &#8599;</a>'); }
                    else if (res) spsAddBot('Error: ' + (res.error || ''));
                });
        };
        /* ---- preview: invoice ---- */
        function spsShowInvoicePreview(f) {
            spsState.pending = { type: 'invoice', fields: f };
            var items = (f.items || []).map(function (it, i) {
                return '<tr><td><input class="ed" id="spi_desc_' + i + '" value="' + String(it.item_description || '').replace(/"/g, '&quot;') + '"></td>'
                    + '<td><input class="ed" id="spi_price_' + i + '" style="width:70px" value="' + (it.unit_price || 0) + '"></td></tr>';
            }).join('');
            var html = '<div class="ai-preview"><h6>New invoice — verify</h6>';
            html += 'Client #: <input class="ed" id="spi_mem" style="width:80px" value="' + (f.mem_id || '') + '"><br>';
            html += 'Due date: <input class="ed" id="spi_due" style="width:130px" value="' + (f.due_date || '') + '">';
            html += '<table style="margin-top:8px"><tr><td><b>Item</b></td><td><b>Unit price</b></td></tr>' + items + '</table>';
            html += '<div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmInvoice()">Confirm &amp; Create</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
        }
        window.spsConfirmInvoice = function () {
            var p = spsState.pending; if (!p) return;
            var f = p.fields; var items = [];
            (f.items || []).forEach(function (it, i) {
                var d = document.getElementById('spi_desc_' + i); var q = document.getElementById('spi_price_' + i);
                if (d && q) items.push({ item_description: d.value, unit_price: q.value, quantity: 1 });
            });
            var fd = new FormData(); fd.append('action', 'preview_invoice');
            fd.append('fields', JSON.stringify({ mem_id: document.getElementById('spi_mem').value, due_date: document.getElementById('spi_due').value, billing_cycle: f.billing_cycle || '', items: items }));
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_invoice'); f2.append('confirmed', '1'); f2.append('fields', JSON.stringify(res.preview));
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) { if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '')); } else if (res) spsAddBot('Error: ' + (res.error || '')); });
        };
        /* ---- preview: instant invoice ---- */
        function spsShowInstantPreview(f) {
            spsState.pending = { type: 'instant', fields: f };
            var items = (f.items || []).map(function (it, i) {
                return '<tr><td><input class="ed" id="spn_desc_' + i + '" value="' + String(it.item_description || '').replace(/"/g, '&quot;') + '"></td>'
                    + '<td><input class="ed" id="spn_price_' + i + '" style="width:70px" value="' + (it.unit_price || 0) + '"></td></tr>';
            }).join('');
            var row = function (idr, k, v) { return '<tr><td><b>' + k + '</b></td><td><input class="ed" id="' + idr + '" value="' + String(v == null ? '' : v).replace(/"/g, '&quot;') + '"></td></tr>'; };
            var html = '<div class="ai-preview"><h6>New instant invoice — verify</h6><table>'
                + row('spn_value_name', 'Name', f.name) + row('spn_value_business_name', 'Business', f.business_name) + row('spn_value_email', 'Email', f.email) + row('spn_value_phone', 'Phone', f.phone) + row('spn_value_address', 'Address', f.address) + row('spn_value_city', 'City', f.city) + row('spn_value_state', 'State', f.state) + row('spn_value_zip', 'Zip', f.zip) + row('spn_value_due_date', 'Due date', f.due_date) + row('spn_value_ssn', 'SSN', f.ssn)
                + '</table><table style="margin-top:8px"><tr><td><b>Item</b></td><td><b>Unit price</b></td></tr>' + items + '</table>';
            html += '<div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmInstant()">Confirm &amp; Create</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
        }
        window.spsConfirmInstant = function () {
            var p = spsState.pending; if (!p) return;
            var f = p.fields; var items = [];
            (f.items || []).forEach(function (it, i) {
                var d = document.getElementById('spn_desc_' + i); var q = document.getElementById('spn_price_' + i);
                if (d && q) items.push({ item_description: d.value, unit_price: q.value, quantity: 1 });
            });
            var data = { name: V('spn_value_name'), business_name: V('spn_value_business_name'), email: V('spn_value_email'), phone: V('spn_value_phone'), address: V('spn_value_address'), city: V('spn_value_city'), state: V('spn_value_state'), zip: V('spn_value_zip'), due_date: V('spn_value_due_date'), ssn: V('spn_value_ssn'), items: items };
            var fd = new FormData(); fd.append('action', 'preview_instant'); fd.append('fields', JSON.stringify(data));
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_instant'); f2.append('confirmed', '1'); f2.append('fields', JSON.stringify(res.preview));
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) { if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '')); } else if (res) spsAddBot('Error: ' + (res.error || '')); });
        };
        function V(id) { var el = document.getElementById(id); return el ? el.value : ''; }
        /* ---- preview: project ---- */
        function spsShowProjectPreview(f) {
            spsState.pending = { type: 'project', fields: f };
            var row = function (idr, k, v) { return '<tr><td><b>' + k + '</b></td><td><input class="ed" id="' + idr + '" value="' + String(v == null ? '' : v).replace(/"/g, '&quot;') + '"></td></tr>'; };
            var html = '<div class="ai-preview"><h6>New project — verify</h6><table>'
                + row('spp_mem', 'Client #', f.mem_id) + row('spp_title', 'Title', f.project_title) + row('spp_desc', 'Description', f.project_description) + row('spp_status', 'Status', f.project_status || 'open') + row('spp_type', 'Type', f.project_type)
                + '</table><div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmProject()">Confirm &amp; Create</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
        }
        window.spsConfirmProject = function () {
            var p = spsState.pending; if (!p) return;
            var fd = new FormData(); fd.append('action', 'preview_project');
            fd.append('fields', JSON.stringify({ mem_id: V('spp_mem'), prod_id: p.fields.prod_id || 0, project_title: V('spp_title'), project_description: V('spp_desc'), project_status: V('spp_status'), project_type: V('spp_type'), start_date: p.fields.start_date || '' }));
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_project'); f2.append('confirmed', '1'); f2.append('fields', JSON.stringify(res.preview));
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) { if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '')); } else if (res) spsAddBot('Error: ' + (res.error || '')); });
        };
        /* ---- preview: estimate ---- */
        function spsShowEstimatePreview(f) {
            spsState.pending = { type: 'estimate', fields: f };
            var row = function (idr, k, v) { return '<tr><td><b>' + k + '</b></td><td><input class="ed" id="' + idr + '" value="' + String(v == null ? '' : v).replace(/"/g, '&quot;') + '"></td></tr>'; };
            var html = '<div class="ai-preview"><h6>New estimate — verify</h6><table>'
                + row('spe_name', 'Name', f.name) + row('spe_phone', 'Phone', f.phone) + row('spe_email', 'Email', f.email) + row('spe_address', 'Address', f.address) + row('spe_services', 'Services', f.services) + row('spe_sub', 'Sub total', f.sub_total) + row('spe_disc', 'Discount', f.discount) + row('spe_grand', 'Grand total', f.grand_total)
                + '</table><div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmEstimate()">Confirm &amp; Create</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
        }
        window.spsConfirmEstimate = function () {
            var p = spsState.pending; if (!p) return;
            var fd = new FormData(); fd.append('action', 'preview_estimate');
            fd.append('fields', JSON.stringify({ member_id: p.fields.member_id || 0, name: V('spe_name'), phone: V('spe_phone'), email: V('spe_email'), address: V('spe_address'), services: V('spe_services'), sub_total: V('spe_sub'), discount: V('spe_disc'), grand_total: V('spe_grand') }));
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_estimate'); f2.append('confirmed', '1'); f2.append('fields', JSON.stringify(res.preview));
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) { if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '')); } else if (res) spsAddBot('Error: ' + (res.error || '')); });
        };
        /* ---- preview: plan ---- */
        function spsShowPlanPreview(f) {
            spsState.pending = { type: 'plan', fields: f };
            var items = (f.items || []).map(function (it, i) {
                return '<tr><td>' + String(it.service_name || '').replace(/</g, '&lt;') + '</td><td>$' + (it.amount || 0) + '</td><td>' + (it.frequency || 'Yearly') + '</td></tr>';
            }).join('');
            var html = '<div class="ai-preview"><h6>New payment plan — verify</h6>';
            html += 'Client #: <input class="ed" id="spl_mem" style="width:80px" value="' + (f.client_id || '') + '"> &nbsp; Plan: <input class="ed" id="spl_name" style="width:140px" value="' + String(f.plan_name || '').replace(/"/g, '&quot;') + '"><br>';
            html += 'Type: <input class="ed" id="spl_type" style="width:110px" value="' + String(f.plan_type || 'Standard').replace(/"/g, '&quot;') + '"> &nbsp; Cycle: <input class="ed" id="spl_cycle" style="width:100px" value="' + String(f.billing_cycle || 'Monthly').replace(/"/g, '&quot;') + '"> &nbsp; Method: <input class="ed" id="spl_method" style="width:90px" value="' + String(f.payment_method || '').replace(/"/g, '&quot;') + '"><br>';
            html += 'Total: $<input class="ed" id="spl_total" style="width:90px" value="' + (f.total_amount || 0) + '">';
            html += '<table style="margin-top:8px"><tr><td><b>Item</b></td><td><b>Amount</b></td><td><b>Freq</b></td></tr>' + items + '</table>';
            html += '<div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmPlan()">Confirm &amp; Create</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
        }
        window.spsConfirmPlan = function () {
            var p = spsState.pending; if (!p) return;
            var fd = new FormData(); fd.append('action', 'preview_plan');
            fd.append('fields', JSON.stringify({ client_id: V('spl_mem'), plan_name: V('spl_name'), plan_type: V('spl_type'), billing_cycle: V('spl_cycle'), payment_method: V('spl_method'), total_amount: V('spl_total'), start_date: p.fields.start_date || '', end_date: p.fields.end_date || '', items: p.fields.items || [] }));
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_plan'); f2.append('confirmed', '1'); f2.append('fields', JSON.stringify(res.preview));
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) { if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '')); } else if (res) spsAddBot('Error: ' + (res.error || '')); });
        };
        /* ---- preview: note ---- */
        function spsShowNotePreview(mid, note) {
            spsState.pending = { type: 'note', member_id: mid, text_note: note };
            var html = '<div class="ai-preview"><h6>Add note to client #' + mid + '</h6>';
            html += '<textarea class="ed" id="spn_note" rows="3">' + String(note || '').replace(/</g, '&lt;') + '</textarea>';
            html += '<div class="ai-confirmwrap"><button class="ai-confirm" onclick="spsConfirmNote()">Confirm &amp; Add</button><button class="ai-cancel" onclick="spsCancel()">Cancel</button></div></div>';
            spsAddBot(html, false);
        }
        window.spsConfirmNote = function () {
            var p = spsState.pending; if (!p) return;
            var noteEl = document.getElementById('spn_note');
            var fd = new FormData(); fd.append('action', 'preview_note'); fd.append('member_id', p.member_id); fd.append('text_note', noteEl ? noteEl.value : p.text_note);
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { spsAddBot('Error: ' + (res.error || '')); return; }
                    var f2 = new FormData(); f2.append('action', 'confirm_note'); f2.append('confirmed', '1'); f2.append('member_id', res.preview.member_id); f2.append('text_note', res.preview.note);
                    return fetch('ai_ajax.php', { method: 'POST', body: f2 });
                })
                .then(function (r) { return r ? r.json() : null; })
                .then(function (res) { if (res && res.ok) { spsState.pending = null; spsAddBot('<b>Done!</b> ' + (res.message || '')); } else if (res) spsAddBot('Error: ' + (res.error || '')); });
        };
        /* ---- attach file ---- */
        function spsShowAttach(mid, hint, notes) {
            spsState.pending = { type: 'attach', member_id: mid, notes: (notes || '') };
            spsAddBot('<div class="ai-preview"><h6>Attach file to client #' + mid + '</h6><div style="font-size:12px">Upload the file below to attach it. ' + String(hint || '').replace(/</g, '&lt;') + '</div></div>', false);
        }
        function spsCancel() { spsState.pending = null; spsAddBot('Cancelled.'); }
        window.spsCancel = spsCancel;

        /* ---- file upload (image OCR / pdf / doc / excel / csv) ---- */
        function spsUpload(input) {
            var file = input.files[0]; if (!file) return;
            document.getElementById('spsImgName').textContent = 'Uploading: ' + file.name + '…';
            spsAddUser('[Uploaded file: ' + file.name + ']');
            spsAddBot('<i>Reading ' + file.name + '…</i>', false);
            var fd = new FormData();
            if (spsState.pending && spsState.pending.type === 'attach') {
                fd.append('action', 'file_attach');
                fd.append('member_id', spsState.pending.member_id);
                fd.append('filename', file.name);
                fd.append('notes', spsState.pending.notes || '');
                fd.append('file', file);
                fetch('ai_ajax.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        spsRemoveThinking();
                        document.getElementById('spsImgName').textContent = '';
                        if (!res.ok) { spsAddBot('Attach error: ' + (res.error || '')); return; }
                        spsState.pending = null;
                        spsAddBot('<b>Attached!</b> ' + (res.message || ''));
                    })
                    .catch(function () { document.getElementById('spsImgName').textContent = ''; spsAddBot('Attach network error.'); });
                input.value = '';
                return;
            }
            fd.append('action', 'upload_file');
            fd.append('file', file);
            fd.append('mode', spsState.mode);
            fetch('ai_ajax.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    spsRemoveThinking();
                    document.getElementById('spsImgName').textContent = '';
                    if (!res.ok) { spsAddBot('Upload error: ' + (res.error || '')); return; }
                    spsHandle(res);
                })
                .catch(function () { document.getElementById('spsImgName').textContent = ''; spsAddBot('Upload network error.'); });
            input.value = '';
        }
        window.spsUpload = spsUpload;

        /* ---- voice talk ---- */
        window.spsVoice = function () {
            var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) { spsAddBot('Voice is not supported in this browser.'); return; }
            var rec = new SR();
            rec.lang = 'en-US'; rec.interimResults = true;
            var mic = document.getElementById('spsMic');
            mic.classList.add('rec');
            var lastInterim = '';
            spsAddBot('Listening… speak now.', false);
            rec.onresult = function (e) {
                var spoken = '';
                for (var i = 0; i < e.results.length; i++) spoken += e.results[i][0].transcript;
                document.getElementById('spsIn').value = spoken;
                lastInterim = spoken;
            };
            rec.onerror = function () { mic.classList.remove('rec'); spsAddBot('Voice error — you can type instead.'); };
            rec.onend = function () {
                mic.classList.remove('rec');
                var v = lastInterim || document.getElementById('spsIn').value;
                if (v && v.trim()) spsSend();
            };
            rec.start();
        };

        /* ---- init ---- */
        spsApplyOpen();
        document.getElementById('spsFab').addEventListener('click', spsOpen);
        if (localStorage.getItem('spsAssistOpen') === '1') spsCheckMode();
    })();
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

                <!--------------------- Overview ----------------------------------->
                <li class="dash-caption">Overview</li>

                <li class="dash-item dash-hasmenu ">
                    <a href="index.php" class="dash-link ">
                        <span class="dash-micon"><i class="ti ti-home"></i></span>
                        <span class="dash-mtext">Dashboard</span>
                    </a>
                </li>

                <?php if (function_exists('ai_can_use') && ai_can_use()) { ?>
                <li class="dash-item dash-hasmenu ">
                    <a href="ai.php" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-robot"></i></span>
                        <span class="dash-mtext">SPS Assistant</span>
                        <span class="badge bg-primary ms-auto">AI</span>
                    </a>
                </li>
                <?php } ?>

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
                                    <i class="ti ti-brain"></i> SPS Assistant
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

                <!--------------------- Clients & Customers -------------------------->
                <li class="dash-caption">Clients &amp; Customers</li>

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

                <!--------------------- Billing & Payments -------------------------->
                <li class="dash-caption">Billing &amp; Payments</li>

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="#" class="dash-link">
                            <span class="dash-micon"><i class="ti ti-align-box-center-middle-filled"></i></span>
                            <span class="dash-mtext">Invoice &amp; Billing</span>
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

                <?php if ($acc_estimates_and_qutations == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="quotations.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-file-invoice"></i></span><span
                                class="dash-mtext">Estimate/Quotes</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="report.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-file-invoice"></i></span><span
                                class="dash-mtext">Report</span>
                        </a>
                    </li>
                <?php } ?>

                <!--------------------- Projects & Renewals -------------------------->
                <li class="dash-caption">Projects &amp; Renewals</li>

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

                <?php if ($acc_view_product == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="reminder_calender.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-calendar"></i></span>
                            <span class="dash-mtext">Reminders</span>
                        </a>
                    </li><?php } ?>

                <!--------------------- Communication -------------------------->
                <li class="dash-caption">Communication</li>

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

                <!--------------------- Tools & Extras -------------------------->
                <li class="dash-caption">Tools &amp; Extras</li>

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
                        <a href="#" class="dash-link" onclick="if(window.spsOpen){event.preventDefault();spsOpen();}">
                            <span class="dash-micon"><i class="ti ti-message-circle-bolt"></i></span><span
                                class="dash-mtext">SPS Assistant</span>
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

                <!--------------------- System -------------------------->
                <li class="dash-caption">System</li>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="manage_roles.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-user-shield"></i></span><span
                                class="dash-mtext">Roles &amp; Permissions</span>
                        </a>
                    </li>
                    <li class="dash-item dash-hasmenu">
                        <a href="settings.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-settings-cog"></i></span><span
                                class="dash-mtext">System Settings</span>
                        </a>
                    </li>
                    <li class="dash-item dash-hasmenu">
                        <a href="automation.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-workflow"></i></span><span
                                class="dash-mtext">Automation</span>
                        </a>
                    </li>
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