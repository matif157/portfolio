<?php
/**
 * Edit Client — with Client Types + Business Details
 * Replace your existing clients/edit.php with this file
 */
require_once __DIR__ . '/../includes/log_in.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_permission('Client', 'edit');
$pageTitle = 'Edit Client';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { set_flash('Client not found.','danger'); header('Location: index.php'); exit; }

$biz = $pdo->prepare("SELECT * FROM business_details WHERE member_id = ? LIMIT 1");
$biz->execute([$id]);
$business = $biz->fetch();

$types = [];
try {
    $types = $pdo->query("SELECT id, type_name FROM client_types WHERE is_active=1 ORDER BY sort_order, type_name")->fetchAll();
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
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $is_guest    = isset($_POST['is_guest']) ? 1 : 0;
    $client_type_id = (int)($_POST['client_type_id'] ?? 0) ?: null;

    $hasTypeCol = false;
    try { $pdo->query("SELECT client_type_id FROM members LIMIT 1"); $hasTypeCol = true; } catch (Exception $e) {}

    if ($hasTypeCol) {
        $pdo->prepare("UPDATE members SET member_name=?, bus_name=?, prm_email=?, phone=?, cell_ph=?, address=?, city=?, state=?, zip=?, notes=?, is_active=?, is_guest=?, client_type_id=? WHERE id=?")
            ->execute([$member_name, $bus_name, $prm_email, $phone, $cell_ph, $address, $city, $state, $zip, $notes, $is_active, $is_guest, $client_type_id, $id]);
    } else {
        $pdo->prepare("UPDATE members SET member_name=?, bus_name=?, prm_email=?, phone=?, cell_ph=?, address=?, city=?, state=?, zip=?, notes=?, is_active=?, is_guest=? WHERE id=?")
            ->execute([$member_name, $bus_name, $prm_email, $phone, $cell_ph, $address, $city, $state, $zip, $notes, $is_active, $is_guest, $id]);
    }

    // Upsert business details
    $president_name = trim($_POST['president_name'] ?? '');
    $bus_street = trim($_POST['bus_street'] ?? '');
    $bus_city   = trim($_POST['bus_city'] ?? '');
    $bus_state  = trim($_POST['bus_state'] ?? '');
    $bus_zip    = trim($_POST['bus_zip'] ?? '');
    $client_since = $_POST['client_since'] ?? null;

    if ($business) {
        $pdo->prepare("UPDATE business_details SET bus_name=?, president_name=?, bus_street=?, bus_city=?, bus_state=?, bus_zip=?, client_since=? WHERE member_id=?")
            ->execute([$bus_name, $president_name, $bus_street, $bus_city, $bus_state, $bus_zip, $client_since ?: null, $id]);
    } elseif ($president_name || $bus_street) {
        $pdo->prepare("INSERT INTO business_details (member_id, bus_name, president_name, bus_street, bus_city, bus_state, bus_zip, client_since, user_id, status) VALUES (?,?,?,?,?,?,?,?,?,1)")
            ->execute([$id, $bus_name, $president_name, $bus_street, $bus_city, $bus_state, $bus_zip, $client_since ?: null, $_SESSION['user_id']]);
    }

    log_activity('update_client', 'Client', "Updated client: $member_name (ID $id)");
    set_flash('Client updated successfully.');
    header("Location: view.php?id=$id");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <span>Edit Client #<?= $id ?></span>
                <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-light">View Profile</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Basic Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="member_name" class="form-control" value="<?= e($client['member_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="bus_name" class="form-control" value="<?= e($client['bus_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Client Type</label>
                            <select name="client_type_id" class="form-select">
                                <option value="">— Select —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= ($client['client_type_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= e($t['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="prm_email" class="form-control" value="<?= e($client['prm_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($client['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cell</label>
                            <input type="text" name="cell_ph" class="form-control" value="<?= e($client['cell_ph'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?= e($client['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= e($client['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?= e($client['state'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZIP</label>
                            <input type="text" name="zip" class="form-control" value="<?= e($client['zip'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= e($client['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $client['is_active']?'checked':'' ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_guest" id="is_guest" <?= $client['is_guest']?'checked':'' ?>>
                                <label class="form-check-label" for="is_guest">Guest Client</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Business Details</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">President / Owner</label>
                            <input type="text" name="president_name" class="form-control" value="<?= e($business['president_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Since</label>
                            <input type="date" name="client_since" class="form-control" value="<?= e($business['client_since'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Business Street</label>
                            <input type="text" name="bus_street" class="form-control" value="<?= e($business['bus_street'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="bus_city" class="form-control" value="<?= e($business['bus_city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="bus_state" class="form-control" value="<?= e($business['bus_state'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZIP</label>
                            <input type="text" name="bus_zip" class="form-control" value="<?= e($business['bus_zip'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Client</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
