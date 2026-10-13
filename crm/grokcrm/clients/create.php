<?php
/**
 * Add Client — with Client Types + Customizer defaults
 * Replace your existing clients/create.php with this file
 */
require_once __DIR__ . '/../includes/log_in.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_permission('Client', 'add');
$pageTitle = 'Add Client';

// Client types
$types = [];
try {
    $types = $pdo->query("SELECT id, type_name, color FROM client_types WHERE is_active=1 ORDER BY sort_order, type_name")->fetchAll();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_name = trim($_POST['member_name'] ?? '');
    $bus_name    = trim($_POST['bus_name'] ?? '');
    $prm_email   = trim($_POST['prm_email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $cell_ph     = trim($_POST['cell_ph'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $state       = trim($_POST['state'] ?? '');
    $zip         = trim($_POST['zip'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $is_guest    = isset($_POST['is_guest']) ? 1 : 0;
    $client_type_id = (int)($_POST['client_type_id'] ?? 0) ?: null;

    // Business details
    $president_name = trim($_POST['president_name'] ?? '');
    $bus_street = trim($_POST['bus_street'] ?? '');
    $bus_city   = trim($_POST['bus_city'] ?? '');
    $bus_state  = trim($_POST['bus_state'] ?? '');
    $bus_zip    = trim($_POST['bus_zip'] ?? '');
    $client_since = $_POST['client_since'] ?? null;

    if ($member_name === '') {
        set_flash('Member / Client name is required.', 'danger');
        header('Location: create.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        // Check if client_type_id column exists
        $hasTypeCol = false;
        try {
            $pdo->query("SELECT client_type_id FROM members LIMIT 1");
            $hasTypeCol = true;
        } catch (Exception $e) {}

        if ($hasTypeCol) {
            $stmt = $pdo->prepare("INSERT INTO members (member_name, bus_name, prm_email, phone, cell_ph, address, city, state, zip, notes, is_guest, client_type_id) 
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$member_name, $bus_name, $prm_email, $phone, $cell_ph, $address, $city, $state, $zip, $notes, $is_guest, $client_type_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO members (member_name, bus_name, prm_email, phone, cell_ph, address, city, state, zip, notes, is_guest) 
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$member_name, $bus_name, $prm_email, $phone, $cell_ph, $address, $city, $state, $zip, $notes, $is_guest]);
        }
        $newId = $pdo->lastInsertId();

        // Optional business details
        if ($president_name || $bus_street || $bus_name) {
            $pdo->prepare("INSERT INTO business_details (member_id, bus_name, president_name, bus_street, bus_city, bus_state, bus_zip, client_since, user_id, status) 
                           VALUES (?,?,?,?,?,?,?,?,?,1)")
                ->execute([$newId, $bus_name, $president_name, $bus_street, $bus_city, $bus_state, $bus_zip, $client_since ?: null, $_SESSION['user_id']]);
        }

        $pdo->commit();
        log_activity('create_client', 'Client', "Created client: $member_name (ID $newId)");
        set_flash('Client created successfully.');
        header("Location: view.php?id=$newId");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('Error: ' . $e->getMessage(), 'danger');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>Add New Client</span>
                <a href="index.php" class="btn btn-sm btn-outline-light">Back to List</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Basic Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name / Contact *</label>
                            <input type="text" name="member_name" class="form-control" required autofocus>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="bus_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Client Type</label>
                            <select name="client_type_id" class="form-select">
                                <option value="">— Select —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= e($t['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($types)): ?>
                                <div class="form-text text-warning">Run update_tables.sql to enable client types</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="prm_email" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cell</label>
                            <input type="text" name="cell_ph" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mailing Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZIP</label>
                            <input type="text" name="zip" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_guest" id="is_guest">
                                <label class="form-check-label" for="is_guest">Guest / Instant Client (one-time)</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Business Details (optional)</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">President / Owner Name</label>
                            <input type="text" name="president_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Since</label>
                            <input type="date" name="client_since" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Business Street</label>
                            <input type="text" name="bus_street" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Business City</label>
                            <input type="text" name="bus_city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Business State</label>
                            <input type="text" name="bus_state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Business ZIP</label>
                            <input type="text" name="bus_zip" class="form-control">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Save Client</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
