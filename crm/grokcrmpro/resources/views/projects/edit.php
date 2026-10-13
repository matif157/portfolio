<div class="row justify-content-center"><div class="col-lg-8">
<div class="card"><div class="card-header">Edit Project</div><div class="card-body">
<form method="POST" action="<?= url('/projects/'.$project['id']) ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-8"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= e($project['name']) ?>" required></div>
    <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" class="form-select">
            <?php foreach (['draft','open','in_progress','waiting_client','under_review','completed','closed','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $project['status']===$st?'selected':'' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Start</label><input type="date" name="start_date" class="form-control" value="<?= e($project['start_date'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">End</label><input type="date" name="end_date" class="form-control" value="<?= e($project['end_date'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Priority</label>
        <select name="priority" class="form-select">
            <?php foreach (['low','normal','high','urgent'] as $p): ?>
            <option value="<?= $p ?>" <?= $project['priority']===$p?'selected':'' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Billing</label>
        <select name="billing_type" class="form-select">
            <?php foreach (['one_time','recurring','milestone','hourly'] as $bt): ?>
            <option value="<?= $bt ?>" <?= $project['billing_type']===$bt?'selected':'' ?>><?= e($bt) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Manager</label>
        <select name="manager_id" class="form-select">
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $project['manager_id']==$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e($project['description'] ?? '') ?></textarea></div>
</div>
<div class="mt-3">
    <button class="btn btn-primary">Update</button>
    <a href="<?= url('/projects/'.$project['id']) ?>" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div></div></div></div>
