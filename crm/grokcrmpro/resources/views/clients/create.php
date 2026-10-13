<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header">New Client</div>
    <div class="card-body">
        <form method="POST" action="<?= url('/clients') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Company</label><input type="text" name="company_name" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Cell</label><input type="text" name="cell_phone" class="form-control"></div>
                <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">State</label><input type="text" name="state" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">ZIP</label><input type="text" name="zip" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                </div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">Save Client</button>
                <a href="<?= url('/clients') ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
