<?php ob_start(); ?>
<div class="page-head"><div><small>CRM MODULE</small><h1><?=htmlspecialchars($title??'Module')?></h1><p>Manage records within the professional-services workflow.</p></div><button class="primary">+ New Record</button></div>
<div class="toolbar"><input placeholder="Search records..."><select><option>All statuses</option><option>Active</option><option>Pending</option><option>Completed</option></select></div>
<section class="panel"><div class="panel-title"><h2><?=htmlspecialchars($title??'Records')?> Register</h2><button>Export</button></div><div class="table-scroll"><table><thead><tr><th>Reference</th><th>Name / Client</th><th>Service</th><th>Status</th><th>Updated</th></tr></thead><tbody><tr><td>—</td><td>—</td><td>—</td><td><span class="badge">Awaiting database</span></td><td>—</td></tr></tbody></table></div></section>
<?php $content=ob_get_clean(); require __DIR__.'/../layouts/app.php'; ?>
