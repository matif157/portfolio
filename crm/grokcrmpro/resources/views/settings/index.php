<form method="POST" action="<?= url('/settings') ?>">
<?= csrf_field() ?>
<div class="d-flex justify-content-between mb-3">
    <p class="text-muted mb-0">Company & billing defaults used on invoices and estimates.</p>
    <button class="btn btn-primary btn-sm">Save Settings</button>
</div>
<div class="row g-3">
<?php foreach ($groups as $group => $settings): ?>
<div class="col-md-6">
    <div class="card h-100">
        <div class="card-header"><?= e(ucfirst($group)) ?></div>
        <div class="card-body">
            <?php foreach ($settings as $s): ?>
            <div class="mb-3">
                <label class="form-label"><?= e($s['label'] ?: $s['setting_key']) ?></label>
                <input type="text" name="<?= e($s['setting_key']) ?>" class="form-control form-control-sm" value="<?= e($s['setting_value']) ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<div class="mt-3 text-end"><button class="btn btn-primary">Save All</button></div>
</form>
