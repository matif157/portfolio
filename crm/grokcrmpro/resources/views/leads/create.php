<div class="row justify-content-center"><div class="col-lg-8">
<div class="card"><div class="card-header">New Lead</div><div class="card-body">
<form method="POST" action="<?= url('/leads') ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Lead Name *</label><input type="text" name="lead_name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company_name" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Source</label><input type="text" name="source" class="form-control" placeholder="Referral, Web, Walk-in..."></div>
    <div class="col-md-4"><label class="form-label">Service Interest</label>
        <select name="service_interest" class="form-select">
            <option value="">—</option>
            <?php foreach ($services as $s): ?><option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="new">New</option><option value="contacted">Contacted</option>
            <option value="qualified">Qualified</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Priority</label>
        <select name="priority" class="form-select">
            <option value="low">Low</option><option value="normal" selected>Normal</option>
            <option value="high">High</option><option value="urgent">Urgent</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Estimated Value</label><input type="number" step="0.01" name="estimated_value" class="form-control" value="0"></div>
    <div class="col-md-3"><label class="form-label">Assigned To</label>
        <select name="assigned_user_id" class="form-select">
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
</div>
<div class="mt-3">
    <button class="btn btn-primary">Save Lead</button>
    <a href="<?= url('/leads') ?>" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div></div></div></div>
