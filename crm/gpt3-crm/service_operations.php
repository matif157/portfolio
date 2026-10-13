<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';
require __DIR__.'/functions.php';

require_login();
$pdo=db();
$clientServiceId=(int)($_GET['client_service_id']??0);
$reqs=[];
if($clientServiceId){
  $s=$pdo->prepare("SELECT csr.id,csr.status,csr.notes,sr.name,sr.requirement_type,sr.is_required
                    FROM client_service_requirements csr
                    JOIN service_requirements sr ON sr.id=csr.requirement_id
                    WHERE csr.client_service_id=? ORDER BY sr.sort_order,sr.id");
  $s->execute([$clientServiceId]); $reqs=$s->fetchAll();
}
$reminders=(new App\Services\ReminderAutomationService($pdo))->upcomingReminders(7);
$title='Service Operations';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<style>
body{margin:0;background:#f5f7fb;color:#18212f;font-family:Arial,sans-serif}
.wrap{max-width:1200px;margin:30px auto;padding:0 16px}.card{background:#fff;border:1px solid #e4e8ee;border-radius:12px;padding:20px;margin-bottom:16px}
h1,h2{margin-top:0}.req{display:flex;justify-content:space-between;gap:20px;padding:12px 0;border-bottom:1px solid #edf0f4}
.badge{padding:5px 9px;border-radius:999px;font-size:12px;background:#eef2f7}.ok{background:#dcfce7;color:#166534}.warn{background:#fef3c7;color:#92400e}
button{padding:8px 12px;border:0;border-radius:7px;background:#111827;color:#fff;cursor:pointer}
small{color:#64748b}
</style>
</head>
<body><div class="wrap">
<div class="card"><h1>Service Operations</h1><p>Manage missing requirements and upcoming client reminders.</p></div>

<div class="card">
<h2>Requirements</h2>
<?php if(!$reqs): ?><p>No requirement records for this service request.</p><?php endif; ?>
<?php foreach($reqs as $q): ?>
<div class="req">
  <div><strong><?=htmlspecialchars($q['name'])?></strong><br><small><?=htmlspecialchars($q['requirement_type'])?> · <?=!empty($q['is_required'])?'Required':'Optional'?></small></div>
  <div>
   <?php if($q['status']==='completed'): ?>
      <span class="badge ok">Completed</span>
   <?php else: ?>
      <span class="badge warn">Missing</span>
      <button data-complete="<?=$q['id']?>">Mark complete</button>
   <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>Upcoming reminders</h2>
<?php if(!$reminders): ?><p>No reminders in the next 7 days.</p><?php endif; ?>
<?php foreach($reminders as $r): ?>
<div class="req">
 <div><strong><?=htmlspecialchars($r['title'])?></strong><br><small><?=htmlspecialchars($r['client_names']??'')?></small></div>
 <div><?=htmlspecialchars($r['start_at'])?></div>
</div>
<?php endforeach; ?>
</div>
</div>

<script>
document.querySelectorAll('[data-complete]').forEach(btn=>{
 btn.addEventListener('click',async()=>{
  const id=btn.dataset.complete;
  const r=await fetch('/client-service-requirements/'+id+'/complete',{method:'POST',body:new FormData()});
  if(r.ok)location.reload();else alert('Unable to update requirement');
 });
});
</script>
</body></html>
