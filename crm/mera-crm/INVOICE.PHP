<?php
// =====================================================
// FILE: invoices.php
// PURPOSE: Invoice Management (listing, search, stats, modals)
// Depends on: log_in.php, sidebar.php, invoice_ajax.php, style.css
// =====================================================

// Load login/session and DB pdoection
require_once 'log_in.php';

/* Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
    */

// Safe output helper to escape HTML
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Get search and status filters from GET
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

// =====================================================
// DASHBOARD STATISTICS
// =====================================================

// Total invoices
$totalInvoices = (int)$pdo
    ->query("SELECT COUNT(*) FROM invoices")
    ->fetchColumn();

// Total revenue
$totalRevenue = (float)$pdo
    ->query("SELECT COALESCE(SUM(grand_total),0) FROM invoices")
    ->fetchColumn();

// Total collected (payments)
$totalPaid = (float)$pdo
    ->query("SELECT COALESCE(SUM(amount),0) FROM invoice_payments")
    ->fetchColumn();

// Pending balance
$pendingAmount = max(0, $totalRevenue - $totalPaid);

// Overdue invoices (due_date past and not paid)
$overdueInvoices = (int)$pdo
    ->query("
        SELECT COUNT(*) FROM invoices
        WHERE due_date < CURDATE()
          AND status <> 'Paid'
    ")
    ->fetchColumn();

// =====================================================
// FETCH INVOICES LIST
// =====================================================

$sql = "
    SELECT i.*, c.full_name, c.business_name
    FROM invoices AS i
    LEFT JOIN clients AS c ON c.client_id = i.client_id
    WHERE 1=1
";
$params = [];

// Apply search filter (invoice number, client name, business name)
if ($search !== '') {
    $sql .= " AND (
        i.invoice_number LIKE ?
        OR c.full_name LIKE ?
        OR c.business_name LIKE ?
    )";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Apply status filter
$allowedStatuses = ['Paid','Partial','Unpaid'];
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

// Order by newest first
$sql .= " ORDER BY i.created_date DESC, i.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// PAYMENT INFO HELPER
// =====================================================
// Returns ['paid'=>float, 'balance'=>float, 'percentage'=>int]
function getPaymentInfo(PDO $pdo, int $invoiceId, float $total) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM invoice_payments
        WHERE invoice_id = ?
    ");
    $stmt->execute([$invoiceId]);
    $paid = (float)$stmt->fetchColumn();
    $balance = max(0, $total - $paid);
    $percentage = 0;
    if ($total > 0) {
        $percentage = round(($paid / $total) * 100);
        if ($percentage > 100) {
            $percentage = 100;
        }
    }
    return [
        'paid'       => $paid,
        'balance'    => $balance,
        'percentage' => $percentage
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management</title>
    <link rel="stylesheet" href="style.css">
    <!-- You may load an external JS (e.g. app.js) here -->
</head>
<body>

 
<div class="main-content">
  <div class="invoice-page">

    <!-- Page Header -->
    <div class="page-header">
      <div class="page-heading">
        <h1>Invoice Management</h1>
        <p>Search, filter and manage your invoices and payments.</p>
      </div>
      <button class="create-btn" type="button" onclick="editInvoice(0)">
        + Create Invoice
      </button>
    </div>

    <!-- Analytics Cards -->
    <div class="analytics-grid">
      <div class="analytics-card">
        <div class="icon">📄</div>
        <div>
          <h4>Total Invoices</h4>
          <h2><?= number_format($totalInvoices) ?></h2>
          <p>Invoice records</p>
        </div>
      </div>
      <div class="analytics-card blue">
        <div class="icon">💰</div>
        <div>
          <h4>Total Revenue</h4>
          <h2>$<?= number_format($totalRevenue, 2) ?></h2>
          <p>All invoice value</p>
        </div>
      </div>
      <div class="analytics-card green">
        <div class="icon">✓</div>
        <div>
          <h4>Collected</h4>
          <h2>$<?= number_format($totalPaid, 2) ?></h2>
          <p>Received payments</p>
        </div>
      </div>
      <div class="analytics-card orange">
        <div class="icon">⏳</div>
        <div>
          <h4>Pending</h4>
          <h2>$<?= number_format($pendingAmount, 2) ?></h2>
          <p>Remaining balance</p>
        </div>
      </div>
      <div class="analytics-card red">
        <div class="icon">⚠</div>
        <div>
          <h4>Overdue</h4>
          <h2><?= number_format($overdueInvoices) ?></h2>
          <p>Need attention</p>
        </div>
      </div>
    </div>

    <!-- Search and Filter -->
    <div class="filter-box">
      <form method="GET" action="invoice.php">
        <input type="text" name="search" placeholder="Search invoice or client..." value="<?= e($search) ?>">
        <select name="status">
          <option value="">All Status</option>
          <option value="Paid" <?= $status==='Paid' ? 'selected' : '' ?>>Paid</option>
          <option value="Partial" <?= $status==='Partial' ? 'selected' : '' ?>>Partial</option>
          <option value="Unpaid" <?= $status==='Unpaid' ? 'selected' : '' ?>>Unpaid</option>
        </select>
        <button type="submit" class="crm-btn">🔍 Search</button>
        <?php if ($search !== '' || $status !== ''): ?>
          <a href="invoice.php" class="reset-btn">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Invoice Table -->
    <div class="table-card">
      <div class="invoice-table-wrapper">
        <table class="invoice-table">
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Client</th>
              <th>Created</th>
              <th>Due</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="8" class="empty">No invoices found.</td></tr>
            <?php else: ?>
              <?php foreach ($invoices as $inv): ?>
                <?php 
                  $payment = getPaymentInfo($pdo, (int)$inv['id'], (float)$inv['grand_total']);
                  // Determine display name
                  $clientName = $inv['business_name'] ?: $inv['full_name'];
                  if (trim($clientName) === '') {
                      $clientName = 'Unknown Client';
                  }
                ?>
                <tr>
                  <!-- Invoice Number (clickable) -->
                  <td>
                    <div class="invoice-hover">
                      <button type="button" class="invoice-number-link"
                              onclick="viewInvoice(<?= (int)$inv['id'] ?>)">
                        #<?= e($inv['invoice_number']) ?>
                      </button>
                      <!-- Hover Preview -->
                      <div class="preview">
                        <h4>Invoice #<?= e($inv['invoice_number']) ?></h4>
                        <div class="preview-line"></div>
                        <p><strong>Client:</strong> <?= e($clientName) ?></p>
                        <p><strong>Amount:</strong> $<?= number_format((float)$inv['grand_total'],2) ?></p>
                        <p><strong>Paid:</strong> $<?= number_format($payment['paid'],2) ?></p>
                        <p><strong>Balance:</strong> $<?= number_format($payment['balance'],2) ?></p>
                        <p><strong>Status:</strong> <?= e($inv['status']) ?></p>
                      </div>
                    </div>
                  </td>
                  <!-- Client Name -->
                  <td><?= e($clientName) ?></td>
                  <!-- Created Date -->
                  <td><?= e($inv['created_date']) ?></td>
                  <!-- Due Date -->
                  <td><?= e($inv['due_date']) ?></td>
                  <!-- Grand Total -->
                  <td><strong>$<?= number_format((float)$inv['grand_total'],2) ?></strong></td>
                  <!-- Payment Progress -->
                  <td>
                    <div class="payment-progress">
                      <div class="progress">
                        <div class="progress-fill" style="width: <?= $payment['percentage'] ?>%;"></div>
                      </div>
                      <small><?= $payment['percentage'] ?>% Paid</small>
                    </div>
                  </td>
                  <!-- Status Badge -->
                  <td>
                    <?php 
                      $st = strtolower($inv['status']);
                      $class = "status-" . $st; 
                    ?>
                    <span class="<?= $class ?>"><?= e($inv['status']) ?></span>
                  </td>
                  <!-- Actions -->
                  <td>
                    <div class="invoice-actions">
                      <button type="button" class="action view" title="View Invoice"
                              onclick="viewInvoice(<?= (int)$inv['id'] ?>)">👁</button>
                      <button type="button" class="action edit" title="Edit Invoice"
                              onclick="editInvoice(<?= (int)$inv['id'] ?>)">✏</button>
                      <button type="button" class="action payment" title="Add Payment"
                              onclick="addPayment(<?= (int)$inv['id'] ?>)">💵</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div> <!-- .invoice-page -->
</div> <!-- .main-content -->

<!-- =========================
     VIEW INVOICE MODAL
   ========================= -->
<div id="viewInvoiceModal" class="crm-modal">
  <div class="crm-modal-box large">
    <button type="button" class="close-modal" onclick="closeModal('viewInvoiceModal')">×</button>
    <div id="invoiceViewContent">
      <div class="loading-box">Loading invoice...</div>
    </div>
  </div>
</div>

<!-- =========================
     EDIT INVOICE MODAL
   ========================= -->
<div id="editInvoiceModal" class="crm-modal">
  <div class="crm-modal-box large">
    <button type="button" class="close-modal" onclick="closeModal('editInvoiceModal')">×</button>
    <div id="invoiceEditContent">
      <div class="loading-box">Loading edit form...</div>
    </div>
  </div>
</div>

<!-- =========================
     ADD PAYMENT MODAL
   ========================= -->
<div id="paymentModal" class="crm-modal">
  <div class="crm-modal-box">
    <button type="button" class="close-modal" onclick="closeModal('paymentModal')">×</button>
    <div id="paymentContent">
      <div class="loading-box">Loading payment form...</div>
    </div>
  </div>
</div>

<!-- =========================
     TOAST NOTIFICATION
   ========================= -->
<div id="crmToast" class="crm-toast"></div>

<!-- =========================
     INLINE SCRIPTS (fallback if app.js not used)
   ========================= -->
<script>
// Open a modal by ID
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("show");
    document.body.classList.add("modal-open");
}

// Close a modal by ID
function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove("show");
    // If no other modal open, restore scrolling
    if (!document.querySelector(".crm-modal.show")) {
        document.body.classList.remove("modal-open");
    }
}

// Show a toast message (auto-fades after 3s)
function showToast(message, type = "success") {
    const toast = document.getElementById("crmToast");
    if (!toast) return;
    toast.textContent = message;
    toast.className = "crm-toast show " + type;
    setTimeout(() => { toast.classList.remove("show"); }, 3000);
}

// Escape HTML (to safely insert error messages)
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Fetch content and insert into container (for modal)
async function fetchModalContent(url, container) {
    try {
        const response = await fetch(url, {
            headers: { "X-Requested-With": "XMLHttpRequest" }
        });
        const text = await response.text();
        if (!response.ok) {
            throw new Error(text || "Request failed");
        }
        container.innerHTML = text;
        return true;
    } catch (error) {
        console.error(error);
        container.innerHTML = `
            <div class="ajax-error">
              <h3>Error loading content</h3>
              <p>${escapeHtml(error.message)}</p>
            </div>`;
        return false;
    }
}

// VIEW INVOICE (AJAX GET)
async function viewInvoice(id) {
    const content = document.getElementById("invoiceViewContent");
    content.innerHTML = '<div class="loading-box"><div class="loading-spinner"></div>Loading invoice details...</div>';
    openModal("viewInvoiceModal");
    // Expect invoice_ajax.php?action=view&id=...
    await fetchModalContent("ajax/view_invoice.php?action=view&id=" + encodeURIComponent(id), content);
}

// EDIT INVOICE (AJAX GET)
async function editInvoice(id) {
    const content = document.getElementById("invoiceEditContent");
    content.innerHTML = '<div class="loading-box"><div class="loading-spinner"></div>Loading edit form...</div>';
    openModal("editInvoiceModal");
    const loaded = await fetchModalContent("ajax/edit_invoice.php?action=edit&id=" + encodeURIComponent(id), content);
    if (loaded) {
        bindEditInvoiceForm();
    }
}

// ADD PAYMENT (AJAX GET)
async function addPayment(id) {
    const content = document.getElementById("paymentContent");
    content.innerHTML = '<div class="loading-box"><div class="loading-spinner"></div>Loading payment form...</div>';
    openModal("paymentModal");
    const loaded = await fetchModalContent("ajax/view_invoice.php?action=payment&id=" + encodeURIComponent(id), content);
    if (loaded) {
        bindPaymentForm();
    }
}

// BIND Edit Invoice FORM Submission (AJAX POST)
// Expected invoice_ajax.php?action=save_edit
function bindEditInvoiceForm() {
    const form = document.getElementById("ajaxEditInvoiceForm");
    if (!form || form.dataset.bound) return;
    form.dataset.bound = "1";
    form.addEventListener("submit", async function(e) {
        e.preventDefault();
        const btn = form.querySelector('[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "Saving...";
        try {
            const formData = new FormData(form);
            formData.set("action", "save_edit");
            const response = await fetch("ajax/save_invoice_edit.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: formData
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || "Save failed");
            }
            showToast(result.message || "Invoice updated.");
            closeModal("editInvoiceModal");
            setTimeout(() => { window.location.reload(); }, 600);
        } catch (err) {
            console.error(err);
            showToast(err.message || "Error saving invoice", "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

// BIND Payment FORM Submission (AJAX POST)
// Expected invoice_ajax.php?action=save_payment
function bindPaymentForm() {
    const form = document.getElementById("ajaxPaymentForm");
    if (!form || form.dataset.bound) return;
    form.dataset.bound = "1";
    form.addEventListener("submit", async function(e) {
        e.preventDefault();
        const btn = form.querySelector('[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "Saving...";
        try {
            const formData = new FormData(form);
            formData.set("action", "save_payment");
            const response = await fetch("invoice_ajax.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: formData
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || "Save failed");
            }
            showToast(result.message || "Payment saved.");
            closeModal("paymentModal");
            setTimeout(() => { window.location.reload(); }, 600);
        } catch (err) {
            console.error(err);
            showToast(err.message || "Error saving payment", "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

// Close modal when clicking outside content
document.querySelectorAll(".crm-modal").forEach(modal => {
    modal.addEventListener("click", function(e) {
        if (e.target === modal) {
            closeModal(modal.id);
        }
    });
});

// Close modal on ESC key
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        const openModalElem = document.querySelector(".crm-modal.show");
        if (openModalElem) {
            closeModal(openModalElem.id);
        }
    }
});
</script>

</body>
</html>
