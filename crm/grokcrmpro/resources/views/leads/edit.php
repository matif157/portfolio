<div class="row justify-content-center"><div class="col-lg-8">
<div class="card"><div class="card-header">Edit Lead</div><div class="card-body">
<form method="POST" action="<?= url('/leads/'.$lead['id']) ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Lead Name *</label><input type="text" name="lead_name" class="form-control" value="<?= e($lead['lead_name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company_name" class="form-control" value="<?= e($lead['company_name'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($lead['email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($lead['phone'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Source</label><input type="text" name="source" class="form-control" value="<?= e($lead['source'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Service Interest</label><input type="text" name="service_interest" class="form-control" value="<?= e($lead['service_interest'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" class="form-select">
            <?php foreach (['new','contacted','qualified','estimate','accepted','converted','lost'] as $st): ?>
            <option value="<?= $st ?>" <?= $lead['status']===$st?'selected':'' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Priority</label>
        <select name="priority" class="form-select">
            <?php foreach (['low','normal','high','urgent'] as $p): ?>
            <option value="<?= $p ?>" <?= $lead['priority']===$p?'selected':'' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Estimated Value</label><input type="number" step="0.01" name="estimated_value" class="form-control" value="<?= e((string)$lead['estimated_value']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Assigned To</label>
        <select name="assigned_user_id" class="form-select">
            <option value="">—</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $lead['assigned_user_id']==$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($lead['notes'] ?? '') ?></textarea></div>
</div>
<div class="mt-3">
    <button class="btn btn-primary">Update</button>
    <a href="<?= url('/leads/'.$lead['id']) ?>" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div></div></div></div>
