<?php
/**
 * Invoice View — with LIVE invoice template preview on the page
 * REPLACE your invoices/view.php with this file
 */
require_once __DIR__ . '/../includes/log_in.php';
if (file_exists(__DIR__ . '/../includes/settings_helper.php')) {
    require_once __DIR__ . '/../includes/settings_helper.php';
}
require_permission('Invoice', 'view');
$pageTitle = 'Invoice Details';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, m.member_name, m.bus_name, m.prm_email, m.phone, m.cell_ph,
                              m.address, m.city, m.state, m.zip
                       FROM invoices i LEFT JOIN members m ON i.mem_id = m.id WHERE i.id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { set_flash('Invoice not found.','danger'); header('Location: index.php'); exit; }

$items = $pdo->prepare("SELECT * FROM invoice_items WHERE inv_id = ? ORDER BY id");
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $pdo->prepare("SELECT p.*, u.full_name FROM payments p LEFT JOIN users u ON p.received_by = u.id WHERE p.invoice_id = ? ORDER BY p.payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$statusLabels = [1=>'UNPAID', 2=>'PARTIAL', 3=>'PAID', 4=>'CANCELLED'];
$statusClass  = [1=>'danger', 2=>'warning', 3=>'success', 4=>'secondary'];
$statusColors = [1=>'#dc3545', 2=>'#fd7e14', 3=>'#198754', 4=>'#6c757d'];
$st = (int)$inv['invoice_status'];

// Company from customizer
$company = function_exists('get_company_info') ? get_company_info() : [
    'name'=>'Syed Professional Services', 'phone'=>'(718) 859-5392', 'fax'=>'(718) 859-2237',
    'email'=>'invoice@syedpro.com', 'street'=>'1012 Coney Island Avenue',
    'city'=>'BROOKLYN', 'state'=>'NY', 'zip'=>'11230'
];

$toName = $inv['member_name'] ?: ($inv['guest_name'] ?? 'Client');
$toBus  = $inv['bus_name'] ?: ($inv['guest_business'] ?? '');
$toEmail = $inv['prm_email'] ?: ($inv['guest_email'] ?? '');
$toPhone = $inv['cell_ph'] ?: ($inv['phone'] ?: ($inv['guest_phone'] ?? ''));
$toAddr = $inv['address'] ?: ($inv['guest_address'] ?? '');
$toCity = trim(($inv['city'] ?? '') . ', ' . ($inv['state'] ?? '') . ' ' . ($inv['zip'] ?? ''), ', ');

$staff = $currentUser['full_name'] ?? '';
$initials = '';
foreach (explode(' ', $staff) as $w) { if ($w !== '') $initials .= strtoupper($w[0]); }

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action bar -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="color:#fff">Invoice #<?= $inv['id'] ?>
            <span class="badge bg-<?= $statusClass[$st] ?? 'secondary' ?> ms-2"><?= $statusLabels[$st] ?? $st ?></span>
        </h4>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (has_permission('Invoice', 'payment') && $inv['balance'] > 0): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="bi bi-cash"></i> Add Payment
        </button>
        <?php endif; ?>
        <?php if (has_permission('Invoice', 'edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <?php if (has_permission('Invoice', 'print') || true): ?>
        <a href="print.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="bi bi-printer"></i> Print / PDF
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT: Live Invoice Template -->
    <div class="col-lg-8">
        <div class="invoice-preview" id="invoicePreview">
            <!-- Header -->
            <div class="inv-header">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="logo-orb-inv">SP</div>
                    <div>
                        <div style="font-weight:700;font-size:15px;color:#1e4d8c;line-height:1.2">
                            <?= htmlspecialchars(strtoupper(explode(' ', $company['name'])[0] ?? 'SYED')) ?>
                        </div>
                        <div style="font-size:11px;color:#c9a227;font-weight:600">PROFESSIONAL SERVICES</div>
                    </div>
                </div>
                <div style="text-align:right;font-size:12px;line-height:1.6">
                    <div><strong>Invoice #:</strong> <?= $inv['id'] ?></div>
                    <div><strong>By:</strong> <?= htmlspecialchars($initials) ?> (<?= (int)$_SESSION['user_id'] ?>)</div>
                    <div><strong>Created:</strong> <?= $inv['invoice_date'] ? date('m/d/Y', strtotime($inv['invoice_date'])) : '-' ?></div>
                    <div><strong>Due:</strong> <?= $inv['due_date'] ? date('m/d/Y', strtotime($inv['due_date'])) : '-' ?></div>
                    <div class="status-badge" style="border-color:<?= $statusColors[$st] ?? '#666' ?>;color:<?= $statusColors[$st] ?? '#666' ?>">
                        <?= $statusLabels[$st] ?? 'STATUS' ?>
                    </div>
                </div>
            </div>

            <!-- From / To -->
            <div style="display:flex;justify-content:space-between;margin-bottom:20px;font-size:12px">
                <div style="width:48%">
                    <div style="color:#666;font-size:11px;text-transform:uppercase;margin-bottom:4px">From:</div>
                    <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($company['name']) ?></div>
                    <div><?= htmlspecialchars($company['street']) ?></div>
                    <div><?= htmlspecialchars($company['city']) ?>, <?= htmlspecialchars($company['state']) ?> <?= htmlspecialchars($company['zip']) ?></div>
                    <div><?= htmlspecialchars($company['phone']) ?></div>
                    <?php if (!empty($company['fax'])): ?><div><?= htmlspecialchars($company['fax']) ?></div><?php endif; ?>
                    <div><?= htmlspecialchars($company['email']) ?></div>
                </div>
                <div style="width:48%;text-align:right">
                    <div style="color:#666;font-size:11px;text-transform:uppercase;margin-bottom:4px">To:</div>
                    <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($toName) ?></div>
                    <?php if ($toBus): ?><div><?= htmlspecialchars($toBus) ?></div><?php endif; ?>
                    <?php if ($toAddr): ?><div><?= htmlspecialchars($toAddr) ?></div><?php endif; ?>
                    <?php if ($toCity && $toCity !== ',  '): ?><div><?= htmlspecialchars($toCity) ?></div><?php endif; ?>
                    <?php if ($toPhone): ?><div><?= htmlspecialchars($toPhone) ?></div><?php endif; ?>
                    <?php if ($toEmail): ?><div><?= htmlspecialchars($toEmail) ?></div><?php endif; ?>
                </div>
            </div>

            <!-- Line items -->
            <table class="items">
                <thead>
                    <tr><th style="width:75%">Service</th><th style="width:25%">Price</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="2" style="text-align:center;color:#999">No line items</td></tr>
                    <?php else: foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($it['item_description']) ?>
                                <?php if (!empty($it['item_sub_description'])): ?>
                                    <div style="font-size:11px;color:#666;margin-top:2px"><?= htmlspecialchars($it['item_sub_description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= money($it['total_price']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div class="totals">
                <table style="width:100%;border-collapse:collapse">
                    <tr>
                        <td>Sub Total:</td>
                        <td><?= money($inv['total_amount'] + $inv['discount']) ?></td>
                    </tr>
                    <?php if ((float)$inv['discount'] > 0): ?>
                    <tr><td>Discount:</td><td>-<?= money($inv['discount']) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float)$inv['paid_amount'] > 0): ?>
                    <tr><td>Paid:</td><td><?= money($inv['paid_amount']) ?></td></tr>
                    <tr><td>Balance:</td><td><?= money($inv['balance']) ?></td></tr>
                    <?php endif; ?>
                    <tr class="grand">
                        <td>Grand Total:</td>
                        <td><?= money($inv['total_amount']) ?></td>
                    </tr>
                </table>
            </div>

            <div style="clear:both;margin-top:40px">
                <div style="font-size:13px;font-weight:600;margin-bottom:6px;color:#444">Description:</div>
                <div style="min-height:30px;color:#555;font-size:12px"><?= htmlspecialchars($inv['payment_details'] ?? '') ?></div>
            </div>

            <div class="footer-thanks">
                Thank you for choosing <?= htmlspecialchars($company['name']) ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Sidebar info + payments -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Client</div>
            <div class="card-body">
                <?php if ($inv['mem_id']): ?>
                    <a href="../clients/view.php?id=<?= $inv['mem_id'] ?>" class="fw-semibold"><?= htmlspecialchars($inv['member_name']) ?></a>
                    <div class="small text-muted"><?= htmlspecialchars($inv['bus_name'] ?? '') ?></div>
                    <div class="small mt-2"><?= htmlspecialchars($inv['prm_email'] ?? '') ?></div>
                    <div class="small"><?= htmlspecialchars($inv['cell_ph'] ?: $inv['phone'] ?: '') ?></div>
                <?php else: ?>
                    <div><?= htmlspecialchars($inv['guest_name'] ?? 'Guest') ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Summary</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Total</span><strong><?= money($inv['total_amount']) ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Paid</span><strong class="text-success"><?= money($inv['paid_amount']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong class="<?= $inv['balance']>0?'text-danger':'' ?>"><?= money($inv['balance']) ?></strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Payments</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Amount</th><th>Method</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No payments</td></tr>
                        <?php else: foreach ($payments as $p): ?>
                            <tr>
                                <td class="small"><?= $p['payment_date'] ? date('d M Y', strtotime($p['payment_date'])) : '-' ?></td>
                                <td class="text-success"><?= money($p['amount']) ?></td>
                                <td class="small"><?= htmlspecialchars($p['payment_method'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<?php if (has_permission('Invoice', 'payment') && $inv['balance'] > 0): ?>
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="../payments/add.php" class="modal-content">
            <input type="hidden" name="invoice_id" value="<?= $id ?>">
            <input type="hidden" name="mem_id" value="<?= $inv['mem_id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment — Invoice #<?= $id ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Amount *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?= $inv['balance'] ?>" max="<?= $inv['balance'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Method</label>
                    <select name="payment_method" class="form-select">
                        <option>Cash</option><option>Check</option><option>Card</option><option>ACH</option><option>Wire</option><option>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Record Payment</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
