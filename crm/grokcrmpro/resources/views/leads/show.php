<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 style="color:#fff" class="mb-0"><?= e($lead['lead_name']) ?>
            <span class="badge bg-secondary ms-2"><?= e($lead['status']) ?></span>
        </h4>
        <small class="text-muted"><?= e($lead['company_name'] ?? '') ?></small>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_permission('lead.update') && $lead['status'] !== 'converted'): ?>
        <a href="<?= url('/leads/'.$lead['id'].'/edit') ?>" class="btn btn-outline-warning btn-sm">Edit</a>
        <?php endif; ?>
        <?php if (has_permission('lead.convert') && $lead['status'] !== 'converted'): ?>
        <form method="POST" action="<?= url('/leads/'.$lead['id'].'/convert') ?>" class="d-inline" onsubmit="return confirm('Convert this lead to a client?')">
            <?= csrf_field() ?>
            <button class="btn btn-success btn-sm">Convert to Client</button>
        </form>
        <?php endif; ?>
        <?php if ($lead['converted_client_id']): ?>
        <a href="<?= url('/clients/'.$lead['converted_client_id']) ?>" class="btn btn-primary btn-sm">View Client</a>
        <?php endif; ?>
        <a href="<?= url('/leads') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-header">Lead Details</div><div class="card-body small">
            <div class="mb-2"><span class="text-muted">Email:</span> <?= e($lead['email'] ?? '-') ?></div>
            <div class="mb-2"><span class="text-muted">Phone:</span> <?= e($lead['phone'] ?? '-') ?></div>
            <div class="mb-2"><span class="text-muted">Source:</span> <?= e($lead['source'] ?? '-') ?></div>
            <div class="mb-2"><span class="text-muted">Interest:</span> <?= e($lead['service_interest'] ?? '-') ?></div>
            <div class="mb-2"><span class="text-muted">Est. Value:</span> <?= money($lead['estimated_value']) ?></div>
            <div class="mb-2"><span class="text-muted">Priority:</span> <?= e($lead['priority']) ?></div>
            <div class="mb-2"><span class="text-muted">Assigned:</span> <?= e($lead['assigned_name'] ?? '-') ?></div>
            <?php if ($lead['notes']): ?><hr><div><?= e($lead['notes']) ?></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header">Workflow</div><div class="card-body small">
            <p class="text-muted mb-2">Lead pipeline:</p>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach (['new','contacted','qualified','estimate','accepted','converted'] as $st): ?>
                <span class="badge <?= $lead['status']===$st?'bg-primary':'bg-secondary' ?>"><?= e($st) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if ($lead['converted_client_id']): ?>
            <p class="mt-3 mb-0">Converted to client: <a href="<?= url('/clients/'.$lead['converted_client_id']) ?>"><?= e($lead['client_name'] ?? 'Client #'.$lead['converted_client_id']) ?></a>
            <br><small class="text-muted"><?= e($lead['converted_at'] ?? '') ?></small></p>
            <?php endif; ?>
        </div></div>
    </div>
</div>
