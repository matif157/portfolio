<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header">Edit Client</div>
    <div class="card-body">
        <form method="POST" action="<?= url('/clients/'.$client['id']) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?= e($client['full_name']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company_name" class="form-control" value="<?= e($client['company_name'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($client['email'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($client['phone'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Cell</label><input type="text" name="cell_phone" class="form-control" value="<?= e($client['cell_phone'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= e($client['address'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= e($client['city'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">State</label><input type="text" name="state" class="form-control" value="<?= e($client['state'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">ZIP</label><input type="text" name="zip" class="form-control" value="<?= e($client['zip'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $client['status']==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= $client['status']==='inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($client['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">Update</button>
                <a href="<?= url('/clients/'.$client['id']) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
