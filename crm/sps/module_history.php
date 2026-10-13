<?php
/*  MODULE HISTORY / INFO
    ----------------------------------------------------------------
    Adds an "info" icon next to a module section header. Clicking it
    opens a modal showing that module's change history, pulled from
    the existing `activity_logs` table (already populated throughout
    client_details.php via logActivity()).

    A second entry point renders the Invoice module's payment history
    from the existing `billing_payment_history` table.

    No new tables are created here — both source tables already exist.
    ------------------------------------------------------------------ */

if (!isset($con)) {
    die('Include this file AFTER $con is available');
}

/* ---------- module → activity_logs mapping ---------- */
function mh_module_config($client_id)
{
    $cid = (int) $client_id;
    return [
        'personal' => [
            'label'   => 'Personal Information',
            'types'   => ['member'],
            'idlist'  => "SELECT $cid",
        ],
        'alerts' => [
            'label'   => 'Alerts',
            'types'   => ['client_warnings'],
            'idlist'  => "SELECT id FROM client_warnings WHERE member_id=$cid",
        ],
        'company' => [
            'label'   => 'Company Information',
            'types'   => ['member'],
            'idlist'  => "SELECT $cid",
        ],
        'orders' => [
            'label'   => 'Associated Services / Products',
            'types'   => ['orders', 'orders_completed', 'Order'],
            'idlist'  => "SELECT order_id FROM orders WHERE mem_id=$cid",
        ],
        'family' => [
            'label'   => 'Family Members',
            'types'   => ['spouse'],
            'idlist'  => "SELECT spouse_id FROM spouse WHERE member_id=$cid",
        ],
        'businesses' => [
            'label'   => 'Associated Businesses',
            'types'   => ['addibusiness', 'business_member_assoc'],
            'idlist'  => "SELECT addibusiness_id FROM addibusiness WHERE member_id=$cid",
        ],
        'comments' => [
            'label'   => 'Comments',
            'types'   => ['comments'],
            'idlist'  => "SELECT comment_id FROM comments WHERE member_id=$cid AND comment_status != 'bank_statement'",
        ],
        'bank_statement_comments' => [
            'label'   => 'Bank Statement Comments',
            'types'   => ['bank_statement_comment'],
            'idlist'  => "SELECT comment_id FROM comments WHERE member_id=$cid AND comment_status = 'bank_statement'",
        ],
        'invoices' => [
            'label'   => 'Invoices',
            'types'   => ['billing_invoice'],
            'idlist'  => "SELECT invoice_id FROM billing_invoice WHERE mem_id=$cid",
        ],
        'invoice_comments' => [
            'label'   => 'Invoice Comments',
            'types'   => ['new_comments', 'Invoice Comment'],
            'idlist'  => "SELECT comment_id FROM new_comments WHERE member_id=$cid",
        ],
        'project_comments' => [
            'label'   => 'Project Comments',
            'types'   => ['project_comments'],
            'idlist'  => "SELECT pc.id FROM project_comments pc JOIN projects p ON p.order_id = pc.project_id WHERE p.mem_id=$cid",
        ],
        'secured_comments' => [
            'label'   => 'Secured Area Comments',
            'types'   => ['secured_comment'],
            'idlist'  => "SELECT scomment_id FROM securedcomments WHERE member_id=$cid",
        ],
        'cards' => [
            'label'   => 'Payment Cards',
            'types'   => ['billing_card'],
            'idlist'  => "SELECT card_id FROM billing_card WHERE mem_id=$cid",
        ],
        'bank_accounts' => [
            'label'   => "Bank Account's Info",
            'types'   => ['bank_account', 'bank_accounts'],
            'idlist'  => "SELECT bank_id FROM bank_accounts WHERE client_id=$cid",
        ],
    ];
}

/* ---------- AJAX: module change history ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'get_module_history') {
    header('Content-Type: application/json');

    $cid    = (int) ($_POST['client_id'] ?? 0);
    $module = $_POST['module'] ?? '';
    $cfg    = mh_module_config($cid);

    if (!$cid || !isset($cfg[$module])) {
        echo json_encode(['success' => false, 'rows' => []]);
        exit;
    }

    $m         = $cfg[$module];
    $typesIn   = "'" . implode("','", array_map(fn($t) => mysqli_real_escape_string($con, $t), $m['types'])) . "'";
    $memberPat = mysqli_real_escape_string($con, "(Member ID|client ID)s?:[[:space:]]*{$cid}([^0-9]|$)");

    $sql = "SELECT l.log_id, l.action, l.description, l.created_at, u.user_name
            FROM activity_logs l
            LEFT JOIN users u ON u.user_id = l.user_id
            WHERE l.object_type IN ($typesIn)
              AND ( l.object_id IN ({$m['idlist']}) OR l.description REGEXP '$memberPat' )
            ORDER BY l.created_at DESC
            LIMIT 200";

    $rows = [];
    $res  = mysqli_query($con, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'action'      => $r['action'],
                'description' => $r['description'],
                'user'        => $r['user_name'] ?? 'Unknown',
                'time'        => date('M d, Y h:i A', strtotime($r['created_at'])),
            ];
        }
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
}

/* ---------- AJAX: invoice payment history ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'get_invoice_payments') {
    header('Content-Type: application/json');

    $cid = (int) ($_POST['client_id'] ?? 0);
    if (!$cid) {
        echo json_encode(['success' => false, 'rows' => []]);
        exit;
    }

    $sql = "SELECT his_id, inv_id, paid, balance, detail, date, time, employee, payment_method
            FROM billing_payment_history
            WHERE mem_id = $cid
            ORDER BY time DESC
            LIMIT 200";

    $rows = [];
    $res  = mysqli_query($con, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'inv_id'         => (int) $r['inv_id'],
                'paid'           => number_format((float) $r['paid'], 2),
                'balance'        => number_format((float) $r['balance'], 2),
                'detail'         => $r['detail'],
                'employee'       => $r['employee'],
                'payment_method' => $r['payment_method'] ?: 'N/A',
                'time'           => date('M d, Y h:i A', strtotime($r['time'])),
            ];
        }
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
}

/* ---------- render helper: the little info icon ---------- */
function mh_icon($module, $label = null)
{
    $label = $label ?? 'History';
    $labelAttr = htmlspecialchars($label, ENT_QUOTES);
    return '<button type="button" class="mh-btn" title="' . $labelAttr . '" onclick="event.stopPropagation(); mhOpen(\'' . $module . '\')"><i class="fas fa-info-circle"></i> ' . $labelAttr . '</button>';
}

function mh_invoice_icon($label = null)
{
    $label = $label ?? 'Payment History';
    $labelAttr = htmlspecialchars($label, ENT_QUOTES);
    return '<button type="button" class="mh-btn" title="' . $labelAttr . '" onclick="event.stopPropagation(); mhOpenInvoicePayments()"><i class="fas fa-info-circle"></i> ' . $labelAttr . '</button>';
}
?>

<?php
/* This file is included near the top of client_details.php / client_billing.php,
   before <!DOCTYPE html> is emitted. Echoing the modal markup below directly would
   print it as raw, unstyled text at the very top of the response (no CSS has
   loaded yet to hide it) — a visible flash of "Change History / Close / Invoice
   Payment History / ..." on every page load. Instead we render it into a buffer,
   then wrap the REST of the page's output in an output-buffer callback that
   splices the modal markup in right after the opening <body> tag, once the page
   is done and PHP flushes the buffer. If no <body> tag ever shows up (e.g. a
   PDF/CSV export triggered further down the script), the buffer is passed
   through untouched. */
if (!isset($_POST['action'])):
    ob_start();
?>
<style>
.mh-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: 10px;
    padding: 4px 10px;
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .02em;
    color: var(--brand, #4361ee);
    background: #fff;
    border: 1px solid var(--brand, #4361ee);
    border-radius: 0;
    cursor: pointer;
    line-height: 1.4;
    vertical-align: middle;
}
.mh-btn i { font-size: .8rem; }
.mh-btn:hover { color: #fff; background: var(--brand, #4361ee); }

.mh-timeline { list-style: none; margin: 0; padding: 0; max-height: 60vh; overflow-y: auto; }
.mh-timeline li {
    padding: 10px 12px;
    border-left: 3px solid var(--brand, #4361ee);
    background: #f8f9fb;
    border-radius: 0;
    margin-bottom: 8px;
    font-size: .85rem;
}
.mh-timeline .mh-meta { color: #888; font-size: .75rem; margin-top: 4px; }
.mh-timeline .mh-action {
    display: inline-block;
    font-weight: 600;
    font-size: .7rem;
    text-transform: uppercase;
    padding: 1px 6px;
    border-radius: 0;
    margin-right: 6px;
    background: var(--brand, #4361ee);
    color: #fff;
}
.mh-timeline .mh-action-create { background: #2e7d32; color: #fff; }
.mh-timeline .mh-action-update { background: #f9c74f; color: #333; }
.mh-timeline .mh-action-delete { background: #d32f2f; color: #fff; }
.mh-empty { text-align: center; color: #999; padding: 30px 0; }

/* ── SweetAlert presentation (square corners, left-aligned body) ── */
.swal2-popup.mh-swal,
.swal2-popup.mh-swal .swal2-styled,
.swal2-popup.mh-swal .swal2-actions button { border-radius: 0 !important; }
.swal2-popup.mh-swal { padding: 0 0 18px; }
.swal2-popup.mh-swal .swal2-title {
    font-size: 1.05rem;
    font-weight: 600;
    text-align: left;
    padding: 18px 22px 14px;
    margin: 0;
    border-bottom: 1px solid #eee;
    width: 100%;
}
.swal2-popup.mh-swal .swal2-html-container {
    text-align: left;
    margin: 16px 22px 0;
    max-height: 62vh;
    overflow-y: auto;
    font-size: .85rem;
}
.swal2-popup.mh-swal .mh-timeline { max-height: none; overflow: visible; }
.swal2-popup.mh-swal .table-responsive { overflow-x: auto; }
.swal2-popup.mh-swal .table { margin-bottom: 0; font-size: .8rem; }
.swal2-popup.mh-swal .table th, .swal2-popup.mh-swal .table td { white-space: nowrap; }
.swal2-popup.mh-swal .table td:nth-child(5) { white-space: normal; min-width: 160px; }
.swal2-popup.mh-swal .swal2-actions { margin: 14px 22px 0; justify-content: flex-end; width: auto; }

/* ── Bootstrap fallback modals (used when SweetAlert isn't available) ── */
#moduleHistoryModal .modal-content,
#invoicePaymentsModal .modal-content,
#moduleHistoryModal .btn,
#invoicePaymentsModal .btn { border-radius: 0; }
</style>

<div class="modal fade" id="moduleHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="moduleHistoryTitle">Change History</h5>
      </div>
      <div class="modal-body">
        <ul class="mh-timeline" id="moduleHistoryList"></ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="invoicePaymentsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Invoice Payment History</h5>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Method</th>
                <th>Detail</th>
                <th>Employee</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody id="invoicePaymentsBody"></tbody>
          </table>
        </div>
        <div class="mh-empty" id="invoicePaymentsEmpty" style="display:none;">No payments recorded yet.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
const MH_CLIENT_ID = <?= (int) $client_id ?>;
const MH_LABELS = {
    personal: 'Personal Information — Change History',
    alerts: 'Alerts — Change History',
    company: 'Company Information — Change History',
    orders: 'Associated Services / Products — Change History',
    family: 'Family Members — Change History',
    businesses: 'Associated Businesses — Change History',
    comments: 'Comments — Change History',
    bank_statement_comments: 'Bank Statement Comments — Change History',
    invoices: 'Invoices — Change History',
    invoice_comments: 'Invoice Comments — Change History',
    project_comments: 'Project Comments — Change History',
    secured_comments: 'Secured Area Comments — Change History',
    cards: 'Payment Cards — Change History',
    bank_accounts: "Bank Account's Info — Change History",
};

/* SweetAlert2 is already loaded on client_details.php / client_billing.php but not on
   every page that uses these buttons (secured_area.php has no scripts.php), so pull in
   the local copy on demand. If it can't be loaded we fall back to the bootstrap modals
   defined above. */
let mhSwalLoader = null;
function mhSwal() {
    if (typeof Swal !== 'undefined') return Promise.resolve(Swal);
    if (!mhSwalLoader) {
        mhSwalLoader = new Promise(resolve => {
            const s = document.createElement('script');
            s.src = 'scripts/sweetalert2.all.min.js';
            s.onload  = () => resolve(typeof Swal !== 'undefined' ? Swal : null);
            s.onerror = () => resolve(null);
            document.head.appendChild(s);
        });
    }
    return mhSwalLoader;
}

function mhPost(params) {
    return fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(params)
    }).then(r => r.json());
}

function mhSetSwalBody(html) {
    const el = document.getElementById('mhSwalBody');
    if (el) el.innerHTML = html;
}

function mhHistoryHtml(rows) {
    return '<ul class="mh-timeline">' + rows.map(row => `
        <li>
            ${row.action ? `<span class="mh-action ${mhActionClass(row.action)}">${row.action}</span>` : ''}${escapeHtmlMh(row.description)}
            <div class="mh-meta">By ${escapeHtmlMh(row.user)} · ${row.time}</div>
        </li>
    `).join('') + '</ul>';
}

function mhPaymentsHtml(rows) {
    return '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr>'
        + '<th>Invoice #</th><th>Paid</th><th>Balance</th><th>Method</th><th>Detail</th><th>Employee</th><th>Date</th>'
        + '</tr></thead><tbody>' + rows.map(row => `
            <tr>
                <td>#${row.inv_id}</td>
                <td class="text-success fw-bold">$${row.paid}</td>
                <td>$${row.balance}</td>
                <td>${escapeHtmlMh(row.payment_method)}</td>
                <td>${escapeHtmlMh(row.detail)}</td>
                <td>${escapeHtmlMh(row.employee)}</td>
                <td>${row.time}</td>
            </tr>
        `).join('') + '</tbody></table></div>';
}

function mhOpen(module) {
    const title = MH_LABELS[module] || 'Change History';
    mhSwal().then(S => {
        if (!S) return mhOpenFallback(module, title);
        S.fire({
            title: title,
            html: '<div id="mhSwalBody"><div class="mh-empty">Loading…</div></div>',
            width: 680,
            confirmButtonText: 'Close',
            customClass: { popup: 'mh-swal' },
            didOpen: () => {
                mhPost({ action: 'get_module_history', client_id: MH_CLIENT_ID, module: module })
                    .then(j => mhSetSwalBody((j.success && j.rows.length)
                        ? mhHistoryHtml(j.rows)
                        : '<div class="mh-empty">No history found.</div>'))
                    .catch(() => mhSetSwalBody('<div class="mh-empty">Failed to load history.</div>'));
            }
        });
    });
}

function mhOpenInvoicePayments() {
    mhSwal().then(S => {
        if (!S) return mhOpenPaymentsFallback();
        S.fire({
            title: 'Invoice Payment History',
            html: '<div id="mhSwalBody"><div class="mh-empty">Loading…</div></div>',
            width: 1040,
            confirmButtonText: 'Close',
            customClass: { popup: 'mh-swal' },
            didOpen: () => {
                mhPost({ action: 'get_invoice_payments', client_id: MH_CLIENT_ID })
                    .then(j => mhSetSwalBody((j.success && j.rows.length)
                        ? mhPaymentsHtml(j.rows)
                        : '<div class="mh-empty">No payments recorded yet.</div>'))
                    .catch(() => mhSetSwalBody('<div class="mh-empty">Failed to load payments.</div>'));
            }
        });
    });
}

function mhOpenFallback(module, title) {
    document.getElementById('moduleHistoryTitle').innerText = title;
    const list = document.getElementById('moduleHistoryList');
    list.innerHTML = '<li class="mh-empty">Loading…</li>';
    $('#moduleHistoryModal').modal('show');

    mhPost({ action: 'get_module_history', client_id: MH_CLIENT_ID, module: module })
    .then(j => {
        if (!j.success || !j.rows.length) {
            list.innerHTML = '<li class="mh-empty">No history found.</li>';
            return;
        }
        list.innerHTML = j.rows.map(row => `
            <li>
                ${row.action ? `<span class="mh-action ${mhActionClass(row.action)}">${row.action}</span>` : ''}${escapeHtmlMh(row.description)}
                <div class="mh-meta">By ${escapeHtmlMh(row.user)} · ${row.time}</div>
            </li>
        `).join('');
    })
    .catch(() => { list.innerHTML = '<li class="mh-empty">Failed to load history.</li>'; });
}

function mhOpenPaymentsFallback() {
    const body = document.getElementById('invoicePaymentsBody');
    const empty = document.getElementById('invoicePaymentsEmpty');
    body.innerHTML = '';
    empty.style.display = 'none';
    $('#invoicePaymentsModal').modal('show');

    mhPost({ action: 'get_invoice_payments', client_id: MH_CLIENT_ID })
    .then(j => {
        if (!j.success || !j.rows.length) {
            empty.style.display = 'block';
            return;
        }
        body.innerHTML = j.rows.map(row => `
            <tr>
                <td>#${row.inv_id}</td>
                <td class="text-success fw-bold">$${row.paid}</td>
                <td>$${row.balance}</td>
                <td>${escapeHtmlMh(row.payment_method)}</td>
                <td>${escapeHtmlMh(row.detail)}</td>
                <td>${escapeHtmlMh(row.employee)}</td>
                <td>${row.time}</td>
            </tr>
        `).join('');
    })
    .catch(() => { empty.style.display = 'block'; empty.innerText = 'Failed to load payments.'; });
}

function mhActionClass(action) {
    switch (String(action).toUpperCase()) {
        case 'CREATE': return 'mh-action-create';
        case 'UPDATE': return 'mh-action-update';
        case 'DELETE': return 'mh-action-delete';
        default: return '';
    }
}

function escapeHtmlMh(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}
</script>
<?php
    $mh_modal_html = ob_get_clean();
    ob_start(function ($buffer) use ($mh_modal_html) {
        $out = preg_replace('/(<body\b[^>]*>)/i', '$1' . $mh_modal_html, $buffer, 1, $count);
        return $count ? $out : $buffer;
    });
endif;
?>
