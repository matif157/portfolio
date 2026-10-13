<?php
/**
 * Customizer / System Settings
 * Drop into: customizer/index.php
 */
require_once __DIR__ . '/../includes/log_in.php';
require_permission('System', 'customizer');
$pageTitle = 'Customizer';

// Ensure settings table exists (safe if already run)
try {
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
} catch (Exception $e) {
    set_flash('Please run database/update_tables.sql first.', 'danger');
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key === 'save_settings') continue;
        if (is_array($value)) continue;
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) 
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([$key, trim($value)]);
    }
    // Checkboxes that were unchecked won't be in POST
    $checkboxes = ['enable_guest_invoices'];
    foreach ($checkboxes as $cb) {
        if (!isset($_POST[$cb])) {
            $pdo->prepare("UPDATE settings SET setting_value='0' WHERE setting_key=?")->execute([$cb]);
        }
    }
    log_activity('update_settings', 'System', 'Updated system settings via Customizer');
    set_flash('Settings saved successfully.');
    header('Location: index.php');
    exit;
}

$rows = $pdo->query("SELECT * FROM settings ORDER BY setting_group, id")->fetchAll();
$groups = [];
foreach ($rows as $r) {
    $groups[$r['setting_group']][] = $r;
}

$groupLabels = [
    'company' => 'Company Information',
    'invoice' => 'Invoice Settings',
    'general' => 'General',
    'theme'   => 'Theme / Appearance',
];

require_once __DIR__ . '/../includes/header.php';
?>

<form method="POST">
    <input type="hidden" name="save_settings" value="1">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Configure company details, invoice defaults, and appearance.</p>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save All Settings</button>
    </div>

    <div class="row g-3">
        <?php foreach ($groups as $group => $settings): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong><?= e($groupLabels[$group] ?? ucfirst($group)) ?></strong>
                </div>
                <div class="card-body">
                    <?php foreach ($settings as $s): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold"><?= e($s['label'] ?: $s['setting_key']) ?></label>
                        <?php if ($s['input_type'] === 'checkbox'): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="<?= e($s['setting_key']) ?>" value="1"
                                       <?= $s['setting_value'] == '1' ? 'checked' : '' ?>>
                            </div>
                        <?php elseif ($s['input_type'] === 'color'): ?>
                            <input type="color" name="<?= e($s['setting_key']) ?>" class="form-control form-control-color"
                                   value="<?= e($s['setting_value'] ?: '#0d6efd') ?>">
                        <?php elseif ($s['input_type'] === 'number'): ?>
                            <input type="number" name="<?= e($s['setting_key']) ?>" class="form-control form-control-sm"
                                   value="<?= e($s['setting_value']) ?>">
                        <?php else: ?>
                            <input type="<?= e($s['input_type'] ?: 'text') ?>" name="<?= e($s['setting_key']) ?>"
                                   class="form-control form-control-sm" value="<?= e($s['setting_value']) ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-save"></i> Save All Settings</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
