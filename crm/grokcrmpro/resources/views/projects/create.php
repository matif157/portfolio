<div class="card"><div class="card-header">New Project</div><div class="card-body">
<form method="POST" action="<?= url('/projects') ?>">
<?= csrf_field() ?>
<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label">Client *</label>
        <select name="client_id" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $preClient==$c['id']?'selected':'' ?>><?= e($c['full_name']) ?><?= $c['company_name']?' — '.e($c['company_name']):'' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-7"><label class="form-label">Project Name *</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-3"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="open">Open</option><option value="in_progress">In Progress</option>
            <option value="draft">Draft</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Priority</label>
        <select name="priority" class="form-select">
            <option value="normal">Normal</option><option value="low">Low</option>
            <option value="high">High</option><option value="urgent">Urgent</option>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Manager</label>
        <select name="manager_id" class="form-select">
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Billing Type</label>
        <select name="billing_type" class="form-select">
            <option value="one_time">One-Time</option><option value="recurring">Recurring</option>
            <option value="milestone">Milestone</option><option value="hourly">Hourly</option>
        </select>
    </div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
</div>
<?php if (!empty($services)): ?>
<h6>Attach Services</h6>
<div class="row g-2 mb-3">
    <?php foreach ($services as $s): ?>
    <div class="col-md-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="service_id[]" value="<?= $s['id'] ?>" id="svc<?= $s['id'] ?>">
            <label class="form-check-label" for="svc<?= $s['id'] ?>"><?= e($s['name']) ?> <small class="text-muted">(<?= money($s['default_price']) ?>)</small></label>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<h6>Assign Staff</h6>
<div class="row g-2 mb-3">
    <?php foreach ($users as $u): ?>
    <div class="col-md-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="staff_id[]" value="<?= $u['id'] ?>" id="st<?= $u['id'] ?>">
            <label class="form-check-label" for="st<?= $u['id'] ?>"><?= e($u['full_name']) ?></label>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<button class="btn btn-primary">Create Project</button>
<a href="<?= url('/projects') ?>" class="btn btn-secondary">Cancel</a>
</form>
</div></div>
