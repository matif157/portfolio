<?php
/**
 * GRAVITY CRM - Invoices Management
 * Phase 2: Core CRM Modules
 * 
 * Yahan invoices create/edit/view, items add, payments record hote hain.
 * Invoice numbers auto-generate hote hain.
 * Status auto-calculate hota hai payments ke basis par.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('invoices', 'view');

$user = current_user();
$page_title = 'Invoices';
$active_page = 'invoices';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('invoices', 'create');
        
        $invoice_id = (int)post('invoice_id', 0);
        $client_id = (int)post('client_id', 0);
        $project_id = (int)post('project_id', 0);
        $invoice_date = sanitize(post('invoice_date'));
        $due_date = sanitize(post('due_date'));
        $discount_type = sanitize(post('discount_type', 'none'));
        $discount_value = (float)post('discount_value', 0);
        $tax_rate = (float)post('tax_rate', 0);
        $notes = sanitize(post('notes'));
        $terms = sanitize(post('terms'));
        
        $errors = [];
        if ($client_id <= 0) {
            $errors[] = 'Please select a client.';
        }
        if ($invoice_date === '') {
            $errors[] = 'Invoice date is required.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad((int)db_value("SELECT COUNT(*) FROM invoices") + 1, 4, '0', STR_PAD_LEFT);
                
                db_execute(
                    'INSERT INTO invoices (client_id, project_id, invoice_number, invoice_date, due_date, discount_type, discount_value, tax_rate, notes, terms, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client_id, $project_id ?: null, $invoice_number, $invoice_date, $due_date ?: null, $discount_type, $discount_value, $tax_rate, $notes, $terms, $user['id']]
                );
                $invoice_id = db_last_id();
                log_activity('invoices', 'create', $invoice_id, 'Created invoice: ' . $invoice_number);
                bus_emit(EVT_INVOICE_CREATED, [], ['source' => 'invoice', 'source_id' => $invoice_id]);
                flash('success', 'Invoice created successfully.');
                redirect('invoices.php?view=' . $invoice_id);
            } else {
                $existing = db_row('SELECT * FROM invoices WHERE id = ?', [$invoice_id]);
                if (!$existing) {
                    flash('error', 'Invoice not found.');
                    redirect('invoices.php');
                }
                
                db_execute(
                    'UPDATE invoices SET client_id = ?, project_id = ?, invoice_date = ?, due_date = ?, discount_type = ?, discount_value = ?, tax_rate = ?, notes = ?, terms = ? WHERE id = ?',
                    [$client_id, $project_id ?: null, $invoice_date, $due_date ?: null, $discount_type, $discount_value, $tax_rate, $notes, $terms, $invoice_id]
                );
                
                // Recalculate totals
                recalculate_invoice($invoice_id);
                
                log_activity('invoices', 'update', $invoice_id, 'Updated invoice: ' . $existing['invoice_number']);
                flash('success', 'Invoice updated successfully.');
                redirect('invoices.php?view=' . $invoice_id);
            }
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'add_item') {
        $invoice_id = (int)post('invoice_id', 0);
        $description = sanitize(post('description'));
        $quantity = max(0, (float)post('quantity', 1));
        $unit_price = (float)post('unit_price', 0);
        $service_id = (int)post('service_id', 0);
        
        $errors = [];
        validate_required($description, 'Item description', $errors);
        if ($unit_price < 0) {
            $errors[] = 'Invalid unit price.';
        }
        
        if (empty($errors)) {
            $line_total = $quantity * $unit_price;
            db_execute(
                'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)',
                [$invoice_id, $service_id ?: null, $description, $quantity, $unit_price, $line_total]
            );
            recalculate_invoice($invoice_id);
            log_activity('invoices', 'add_item', $invoice_id, 'Added item to invoice: ' . $description);
            flash('success', 'Invoice item added.');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
        redirect('invoices.php?view=' . $invoice_id);
    }
    
    if ($action === 'delete_item') {
        $item_id = (int)post('item_id', 0);
        $invoice_id = (int)post('invoice_id', 0);
        
        $item = db_row('SELECT * FROM invoice_items WHERE id = ?', [$item_id]);
        if ($item) {
            db_execute('DELETE FROM invoice_items WHERE id = ?', [$item_id]);
            recalculate_invoice($item['invoice_id']);
            log_activity('invoices', 'delete_item', $item['invoice_id'], 'Removed invoice item: ' . $item['description']);
            flash('success', 'Invoice item removed.');
        }
        redirect('invoices.php?view=' . ($invoice_id ?: ($item['invoice_id'] ?? 0)));
    }
    
    if ($action === 'record_payment') {
        $invoice_id = (int)post('invoice_id', 0);
        $amount = (float)post('amount', 0);
        $payment_date = sanitize(post('payment_date'));
        $payment_method = sanitize(post('payment_method', 'other'));
        $reference = sanitize(post('reference_number'));
        $notes = sanitize(post('notes'));
        
        $invoice = db_row('SELECT * FROM invoices WHERE id = ?', [$invoice_id]);
        if (!$invoice) {
            flash('error', 'Invoice not found.');
            redirect('invoices.php');
        }
        
        if ($amount <= 0) {
            flash('error', 'Payment amount must be greater than zero.');
            redirect('invoices.php?view=' . $invoice_id);
        }
        
        $remaining = $invoice['amount_due'];
        if ($amount > $remaining + 0.001) {
            flash('error', 'Payment exceeds the amount due.');
            redirect('invoices.php?view=' . $invoice_id);
        }
        
        db_execute(
            'INSERT INTO invoice_payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$invoice_id, $payment_date ?: date('Y-m-d'), $amount, $payment_method, $reference_number, $notes, $user['id']]
        );
        
        recalculate_invoice($invoice_id);
        log_activity('invoices', 'payment', $invoice_id, 'Recorded payment of ' . format_currency($amount) . ' on ' . $invoice['invoice_number']);
        bus_emit(EVT_PAYMENT_RECEIVED, [], ['source' => 'invoice_payment', 'source_id' => $invoice_id]);
        flash('success', 'Payment recorded successfully.');
        redirect('invoices.php?view=' . $invoice_id);
    }
    
    if ($action === 'delete_payment') {
        $payment_id = (int)post('payment_id', 0);
        $payment = db_row('SELECT * FROM invoice_payments WHERE id = ?', [$payment_id]);
        if ($payment) {
            db_execute('DELETE FROM invoice_payments WHERE id = ?', [$payment_id]);
            recalculate_invoice($payment['invoice_id']);
            log_activity('invoices', 'delete_payment', $payment['invoice_id'], 'Deleted payment of ' . format_currency($payment['amount']));
            flash('success', 'Payment deleted.');
        }
        redirect('invoices.php?view=' . ($payment['invoice_id'] ?? 0));
    }
    
    if ($action === 'update_status' || $action === 'set_status') {
        $invoice_id = (int)post('invoice_id', 0);
        $status = sanitize(post('status'));
        
        $existing = db_row('SELECT * FROM invoices WHERE id = ?', [$invoice_id]);
        if (!$existing) {
            flash('error', 'Invoice not found.');
            redirect('invoices.php');
        }
        
        db_execute('UPDATE invoices SET status = ? WHERE id = ?', [$status, $invoice_id]);
        log_activity('invoices', 'update_status', $invoice_id, 'Changed invoice ' . $existing['invoice_number'] . ' status to ' . $status);
        bus_emit(EVT_INVOICE_STATUS_CHANGED, [], ['source' => 'invoice', 'source_id' => $invoice_id]);
        flash('success', 'Invoice status updated.');
        redirect('invoices.php?view=' . $invoice_id);
    }
    
    if ($action === 'delete') {
        require_permission('invoices', 'delete');
        $invoice_id = (int)post('invoice_id', 0);
        
        $existing = db_row('SELECT * FROM invoices WHERE id = ?', [$invoice_id]);
        if (!$existing) {
            flash('error', 'Invoice not found.');
            redirect('invoices.php');
        }
        
        // Check if payments exist
        $payments = (int)db_value('SELECT COUNT(*) FROM invoice_payments WHERE invoice_id = ?', [$invoice_id]);
        if ($payments > 0) {
            flash('error', 'Cannot delete invoice with recorded payments. Cancel it instead.');
            redirect('invoices.php');
        }
        
        db_execute('DELETE FROM invoices WHERE id = ?', [$invoice_id]);
        log_activity('invoices', 'delete', $invoice_id, 'Deleted invoice: ' . $existing['invoice_number']);
        flash('success', 'Invoice deleted successfully.');
        redirect('invoices.php');
    }
}

// Filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$status_filter = get('status', '');
if ($status_filter !== '') {
    $where .= ' AND i.status = ?';
    $params[] = $status_filter;
}

$client_filter = (int)get('client', 0);
if ($client_filter > 0) {
    $where .= ' AND i.client_id = ?';
    $params[] = $client_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM invoices i JOIN clients c ON i.client_id = c.id WHERE {$where}", $params);
$invoices = db_query(
    "SELECT i.*, c.first_name, c.last_name 
     FROM invoices i 
     JOIN clients c ON i.client_id = c.id 
     WHERE {$where} 
     ORDER BY i.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_invoices = (int)db_value('SELECT COUNT(*) FROM invoices');
$total_billed = (float)db_value('SELECT COALESCE(SUM(total_amount), 0) FROM invoices');
$total_paid = (float)db_value('SELECT COALESCE(SUM(amount_paid), 0) FROM invoices');
$total_due = (float)db_value('SELECT COALESCE(SUM(amount_due), 0) FROM invoices WHERE status NOT IN ("paid","cancelled")');
$overdue_count = (int)db_value("SELECT COUNT(*) FROM invoices WHERE status NOT IN ('paid','cancelled','draft') AND due_date < CURDATE()");

// Dropdown data
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status != "archived" ORDER BY first_name');
$projects = db_query('SELECT id, name FROM projects WHERE status != "cancelled" ORDER BY name');
$services = db_query('SELECT id, name, billing_type, hourly_rate, fixed_price FROM services WHERE status = "active" ORDER BY name');

// Edit invoice
$edit_invoice = null;
if (get('edit')) {
    $edit_invoice = db_row('SELECT * FROM invoices WHERE id = ?', [(int)get('edit')]);
}

// View invoice
$view_invoice = null;
if (get('view')) {
    $view_invoice = db_row(
        'SELECT i.*, c.first_name AS client_first, c.last_name AS client_last, c.email AS client_email, c.address_line1, c.city, c.state, c.zip, p.name AS project_name 
         FROM invoices i 
         JOIN clients c ON i.client_id = c.id 
         LEFT JOIN projects p ON i.project_id = p.id 
         WHERE i.id = ?',
        [(int)get('view')]
    );
    if ($view_invoice) {
        log_activity('invoices', 'view', $view_invoice['id'], 'Viewed invoice: ' . $view_invoice['invoice_number']);
        $view_items = db_query('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$view_invoice['id']]);
        $view_payments = db_query(
            'SELECT ip.*, u.first_name AS recorded_first, u.last_name AS recorded_last 
             FROM invoice_payments ip 
             LEFT JOIN users u ON ip.recorded_by = u.id 
             WHERE ip.invoice_id = ? 
             ORDER BY ip.payment_date DESC',
            [$view_invoice['id']]
        );
        // Update overdue status if needed
        if ($view_invoice['status'] === 'sent' && $view_invoice['due_date'] && strtotime($view_invoice['due_date']) < time()) {
            db_execute('UPDATE invoices SET status = "overdue" WHERE id = ?', [$view_invoice['id']]);
            $view_invoice['status'] = 'overdue';
        }
    }
}

/**
 * Recalculate invoice totals
 */
function recalculate_invoice(int $invoice_id): void {
    $items = db_query('SELECT COALESCE(SUM(line_total), 0) AS subtotal FROM invoice_items WHERE invoice_id = ?', [$invoice_id]);
    $subtotal = (float)$items[0]['subtotal'];
    
    $invoice = db_row('SELECT * FROM invoices WHERE id = ?', [$invoice_id]);
    if (!$invoice) return;
    
    // Discount
    $discount_amount = 0;
    if ($invoice['discount_type'] === 'percentage' && $invoice['discount_value'] > 0) {
        $discount_amount = $subtotal * ($invoice['discount_value'] / 100);
    } elseif ($invoice['discount_type'] === 'fixed' && $invoice['discount_value'] > 0) {
        $discount_amount = min($subtotal, $invoice['discount_value']);
    }
    
    $taxable = max(0, $subtotal - $discount_amount);
    $tax_amount = $taxable * ($invoice['tax_rate'] / 100);
    $total = $taxable + $tax_amount;
    
    // Payments
    $paid = (float)db_value('SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ?', [$invoice_id]);
    $due = max(0, $total - $paid);
    
    // Status auto-update
    $status = $invoice['status'];
    if ($status !== 'cancelled' && $status !== 'draft') {
        if ($due <= 0.001 && $paid > 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        } elseif ($invoice['status'] === 'overdue' || ($invoice['due_date'] && strtotime($invoice['due_date']) < time())) {
            $status = 'overdue';
        } else {
            $status = 'sent';
        }
    }
    
    db_execute(
        'UPDATE invoices SET subtotal = ?, discount_amount = ?, tax_amount = ?, total_amount = ?, amount_paid = ?, amount_due = ?, status = ? WHERE id = ?',
        [$subtotal, $discount_amount, $tax_amount, $total, $paid, $due, $status, $invoice_id]
    );
    
    // Update project billed amount
    if ($invoice['project_id']) {
        $billed = (float)db_value('SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE project_id = ? AND status NOT IN ("cancelled", "draft")', [$invoice['project_id']]);
        db_execute('UPDATE projects SET billed_amount = ? WHERE id = ?', [$billed, $invoice['project_id']]);
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_invoice): ?>
    <!-- Invoice Profile View -->
    <div class="page-title">
        <h1><i class="bi bi-receipt"></i> Invoice <?= e($view_invoice['invoice_number']) ?></h1>
        <div>
            <a href="invoices.php?edit=<?= $view_invoice['id'] ?>" class="btn-secondary-custom"><i class="bi bi-pencil"></i> Edit</a>
            <a href="invoices.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
            <button type="button" class="btn-secondary-custom" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Invoice Header -->
            <div class="card">
                <div class="card-header">
                    Invoice Details
                    <span><?= status_badge($view_invoice['status']) ?></span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client:</strong> <a href="clients.php?view=<?= $view_invoice['client_id'] ?>"><?= e($view_invoice['client_first'] . ' ' . $view_invoice['client_last']) ?></a></p>
                            <p><strong>Email:</strong> <?= e($view_invoice['client_email']) ?></p>
                            <p><strong>Project:</strong> <?= $view_invoice['project_name'] ? e($view_invoice['project_name']) : 'Not linked' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Invoice Date:</strong> <?= e(format_date($view_invoice['invoice_date'])) ?></p>
                            <p><strong>Due Date:</strong> <?= e(format_date($view_invoice['due_date'])) ?></p>
                            <p><strong>Status:</strong> <?= status_badge($view_invoice['status']) ?></p>
                        </div>
                    </div>
                    <?php if ($view_invoice['notes']): ?>
                        <p><strong>Notes:</strong> <?= e($view_invoice['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Invoice Items -->
            <div class="card">
                <div class="card-header">Line Items (<?= count($view_items) ?>)</div>
                <div class="card-body">
                    <table class="table-custom">
                        <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($view_items)): ?>
                                <tr><td colspan="5" class="text-muted">No items on this invoice yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($view_items as $item): ?>
                                    <tr>
                                        <td><?= e($item['description']) ?></td>
                                        <td><?= (float)$item['quantity'] ?></td>
                                        <td><?= format_currency($item['unit_price']) ?></td>
                                        <td><?= format_currency($item['line_total']) ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Remove this item?')">
                                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-x"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end"><strong>Subtotal:</strong></td><td><strong><?= format_currency($view_invoice['subtotal']) ?></strong></td><td></td></tr>
                            <?php if ($view_invoice['discount_amount'] > 0): ?>
                                <tr><td colspan="3" class="text-end">Discount (<?= $view_invoice['discount_type'] === 'percentage' ? e($view_invoice['discount_value']) . '%' : 'Fixed' ?>):</td><td>-<?= format_currency($view_invoice['discount_amount']) ?></td><td></td></tr>
                            <?php endif; ?>
                            <?php if ($view_invoice['tax_amount'] > 0): ?>
                                <tr><td colspan="3" class="text-end">Tax (<?= e($view_invoice['tax_rate']) ?>%):</td><td><?= format_currency($view_invoice['tax_amount']) ?></td><td></td></tr>
                            <?php endif; ?>
                            <tr><td colspan="3" class="text-end"><strong>Total:</strong></td><td><strong><?= format_currency($view_invoice['total_amount']) ?></strong></td><td></td></tr>
                            <tr><td colspan="3" class="text-end">Paid:</td><td><?= format_currency($view_invoice['amount_paid']) ?></td><td></td></tr>
                            <tr><td colspan="3" class="text-end"><strong>Balance Due:</strong></td><td><strong style="color:#dc2626;"><?= format_currency($view_invoice['amount_due']) ?></strong></td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Add Item Form -->
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle"></i> Add Line Item</div>
                <div class="card-body">
                    <form method="POST" action="" class="row g-3">
                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="invoice_id" value="<?= $view_invoice['id'] ?>">
                        <div class="col-md-4">
                            <label class="form-label-custom">Service (optional)</label>
                            <select name="service_id" class="form-control-custom" onchange="fillService(this)">
                                <option value="0">Custom Item</option>
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?= $svc['id'] ?>" data-price="<?= $svc['fixed_price'] ?: $svc['hourly_rate'] ?>"><?= e($svc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Description *</label>
                            <input type="text" name="description" id="itemDescription" class="form-control-custom" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">Qty</label>
                            <input type="number" name="quantity" step="0.25" value="1" class="form-control-custom" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">Unit Price</label>
                            <input type="number" name="unit_price" id="itemPrice" step="0.01" value="0" class="form-control-custom" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-primary-custom"><i class="bi bi-plus"></i> Add Item</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Payments -->
            <div class="card">
                <div class="card-header">
                    Payments (<?= count($view_payments) ?>)
                    <span class="badge badge-success"><?= format_currency($view_invoice['amount_paid']) ?> received</span>
                </div>
                <div class="card-body">
                    <?php if (empty($view_payments)): ?>
                        <p class="text-muted">No payments recorded yet.</p>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Recorded By</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($view_payments as $payment): ?>
                                    <tr>
                                        <td><?= e(format_date($payment['payment_date'])) ?></td>
                                        <td><?= format_currency($payment['amount']) ?></td>
                                        <td><?= e(ucfirst(str_replace('_', ' ', $payment['payment_method']))) ?></td>
                                        <td><?= e($payment['reference_number'] ?: '-') ?></td>
<td><?= e($payment['recorded_first'] ? $payment['recorded_first'] . ' ' . $payment['recorded_last'] : '-') ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Delete this payment?')">
                                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_payment">
                                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <?php if ($view_invoice['amount_due'] > 0 && $view_invoice['status'] !== 'cancelled'): ?>
                        <hr>
                        <h6><i class="bi bi-cash"></i> Record Payment</h6>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="record_payment">
                            <input type="hidden" name="invoice_id" value="<?= $view_invoice['id'] ?>">
                            <div class="col-md-3">
                                <label class="form-label-custom">Amount *</label>
                                <input type="number" name="amount" step="0.01" min="0.01" max="<?= $view_invoice['amount_due'] ?>" class="form-control-custom" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-custom">Date</label>
                                <input type="date" name="payment_date" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-custom">Method</label>
                                <select name="payment_method" class="form-control-custom">
                                    <option value="check">Check</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="ach">ACH</option>
                                    <option value="cash">Cash</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-custom">Reference #</label>
                                <input type="text" name="reference_number" class="form-control-custom" placeholder="Check #, etc.">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-primary-custom"><i class="bi bi-cash"></i> Record Payment</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Summary -->
            <div class="card">
                <div class="card-header">Summary</div>
                <div class="card-body">
                    <p><strong>Subtotal:</strong> <?= format_currency($view_invoice['subtotal']) ?></p>
                    <?php if ($view_invoice['discount_amount'] > 0): ?>
                        <p><strong>Discount:</strong> -<?= format_currency($view_invoice['discount_amount']) ?></p>
                    <?php endif; ?>
                    <?php if ($view_invoice['tax_amount'] > 0): ?>
                        <p><strong>Tax:</strong> <?= format_currency($view_invoice['tax_amount']) ?></p>
                    <?php endif; ?>
                    <hr>
                    <p class="fs-5"><strong>Total:</strong> <?= format_currency($view_invoice['total_amount']) ?></p>
                    <p><strong>Paid:</strong> <?= format_currency($view_invoice['amount_paid']) ?></p>
                    <p><strong>Balance Due:</strong> <?= format_currency($view_invoice['amount_due']) ?></p>
                </div>
            </div>
            
            <!-- Status Update -->
            <div class="card">
                <div class="card-header">Invoice Status</div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="invoice_id" value="<?= $view_invoice['id'] ?>">
                        <select name="status" class="form-control-custom">
                            <?php foreach (['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $view_invoice['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-secondary-custom btn-block w-100"><i class="bi bi-check"></i> Update Status</button>
                    </form>
                </div>
            </div>
            
            <!-- Terms -->
            <?php if ($view_invoice['terms']): ?>
            <div class="card">
                <div class="card-header">Terms</div>
                <div class="card-body">
                    <p><?= e($view_invoice['terms']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Invoices List -->
    <div class="page-title">
        <h1><i class="bi bi-receipt"></i> Invoices</h1>
        <button class="btn-primary-custom" onclick="toggleNewInvoice()"><i class="bi bi-plus"></i> New Invoice</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-receipt"></i></div>
                <div class="stat-value"><?= $total_invoices ?></div>
                <div class="stat-label">Total Invoices</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value"><?= format_currency($total_paid) ?></div>
                <div class="stat-label">Collected</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-hourglass"></i></div>
                <div class="stat-value"><?= format_currency($total_due) ?></div>
                <div class="stat-label">Outstanding</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-exclamation-circle"></i></div>
                <div class="stat-value"><?= $overdue_count ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </div>
    
    <!-- New/Edit Invoice Form -->
    <div class="card" id="invoiceForm" style="display:<?= $edit_invoice ? 'block' : 'none' ?>;">
        <div class="card-header">
            <?= $edit_invoice ? 'Edit Invoice: ' . e($edit_invoice['invoice_number']) : 'Create New Invoice' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $edit_invoice ? 'update' : 'create' ?>">
                <input type="hidden" name="invoice_id" value="<?= $edit_invoice['id'] ?? 0 ?>">
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Client *</label>
                        <select name="client_id" class="form-control-custom" required>
                            <option value="0">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($edit_invoice['client_id'] ?? 0) == $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['first_name'] . ' ' . $client['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Project (optional)</label>
                        <select name="project_id" class="form-control-custom">
                            <option value="0">No Project</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= ($edit_invoice['project_id'] ?? 0) == $proj['id'] ? 'selected' : '' ?>>
                                    <?= e($proj['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Invoice Date *</label>
                        <input type="date" name="invoice_date" class="form-control-custom" value="<?= e($edit_invoice['invoice_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label-custom">Status</label>
                        <span class="badge badge-<?= $edit_invoice['status'] === 'paid' ? 'success' : 'secondary' ?>"><?= e(ucfirst($edit_invoice['status'] ?? 'draft')) ?></span>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label-custom">Due Date</label>
                        <input type="date" name="due_date" class="form-control-custom" value="<?= e($edit_invoice['due_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Discount Type</label>
                        <select name="discount_type" class="form-control-custom">
                            <option value="none" <?= ($edit_invoice['discount_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                            <option value="percentage" <?= ($edit_invoice['discount_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                            <option value="fixed" <?= ($edit_invoice['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Discount Value</label>
                        <input type="number" name="discount_value" step="0.01" class="form-control-custom" value="<?= e($edit_invoice['discount_value'] ?? 0) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" step="0.01" class="form-control-custom" value="<?= e($edit_invoice['tax_rate'] ?? 0) ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Notes</label>
                        <textarea name="notes" class="form-control-custom" rows="2"><?= e($edit_invoice['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Terms</label>
                        <textarea name="terms" class="form-control-custom" rows="2"><?= e($edit_invoice['terms'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_invoice ? 'Update' : 'Create' ?> Invoice</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleNewInvoice()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control-custom" placeholder="Search by number or client..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control-custom">
                        <option value="">All Status</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="client" class="form-control-custom">
                        <option value="0">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $client_filter == $client['id'] ? 'selected' : '' ?>><?= e($client['first_name'] . ' ' . $client['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                    <a href="invoices.php" class="btn-secondary-custom">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Invoices Table -->
    <div class="card">
        <div class="card-header">Invoices (<?= $total ?>)</div>
        <div class="card-body">
            <?php if (empty($invoices)): ?>
                <p class="text-muted">No invoices found.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Total</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><a href="invoices.php?view=<?= $invoice['id'] ?>"><strong><?= e($invoice['invoice_number']) ?></strong></a></td>
                                <td><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></td>
                                <td><?= e(format_date($invoice['invoice_date'])) ?></td>
                                <td><?= e(format_date($invoice['due_date'])) ?></td>
                                <td><?= format_currency($invoice['total_amount']) ?></td>
                                <td><?= format_currency($invoice['amount_due']) ?></td>
                                <td><?= status_badge($invoice['status']) ?></td>
                                <td>
                                    <a href="invoices.php?view=<?= $invoice['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-eye"></i></a>
                                    <a href="invoices.php?edit=<?= $invoice['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                    <?php if ($invoice['status'] === 'draft'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this invoice?')">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                            <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?= pagination_links($total_pages, $page, 'invoices.php?' . http_build_query(array_filter(['search' => $search, 'status' => $status_filter, 'client' => $client_filter]))) ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleNewInvoice() {
    const form = document.getElementById('invoiceForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function fillService(select) {
    const price = select.options[select.selectedIndex].dataset.price;
    const desc = select.options[select.selectedIndex].text;
    if (price) {
        document.getElementById('itemPrice').value = price;
    }
    if (desc !== 'Custom Item') {
        document.getElementById('itemDescription').value = desc;
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>


