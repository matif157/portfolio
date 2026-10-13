<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';require_login();$pdo=db();
if(!table_exists($pdo,'quotations'))redirect('upgrade_phase5.php');
$msg=flash('success');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $client=post_int('client_id');$desc=post_string('description');$amount=(float)($_POST['amount']??0);
    if(!$client||$desc===''||$amount<=0){flash('success','Client, description and positive amount are required.');redirect('quotations.php');}
    try{
        $pdo->beginTransaction();
        $number='QT-'.date('YmdHis').'-'.random_int(100,999);
        $st=$pdo->prepare('INSERT INTO quotations(client_id,quotation_number,status,issue_date,valid_until,subtotal,total_amount,notes,created_by) VALUES(?,?,?,?,?,?,?, ?,?)');
        $st->execute([$client,$number,'draft',date('Y-m-d'),post_string('valid_until')?:null,$amount,$amount,post_string('notes')?:null,current_user()['id']]);
        $qid=(int)$pdo->lastInsertId();
        $st=$pdo->prepare('INSERT INTO quotation_items(quotation_id,description,quantity,unit_price,line_total) VALUES(?,?,1,?,?)');
        $st->execute([$qid,$desc,$amount,$amount]);
        $pdo->commit();
        activity($pdo,'create','quotations',$qid,'Quotation created: '.$number);
        flash('success','Quotation created.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[SPS QUOTES] '.$e->getMessage());flash('success','Quotation could not be created.');}
    redirect('quotations.php');
}
$clients=$pdo->query("SELECT id,first_name,last_name FROM clients WHERE deleted_at IS NULL ORDER BY last_name")->fetchAll();
$rows=$pdo->query("SELECT q.*,CONCAT(c.first_name,' ',c.last_name) client_name FROM quotations q JOIN clients c ON c.id=q.client_id ORDER BY q.id DESC LIMIT 200")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quotations · SPS CRM</title>
<style>body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1120px;margin:auto;padding:28px}.grid{display:grid;grid-template-columns:1fr 1.7fr;gap:16px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px}.field{margin:10px 0}.field label{display:block;font-size:12px;font-weight:bold;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;padding:10px;border:1px solid #d8dfeb;border-radius:8px}.btn{background:#315ee7;color:#fff;border:0;padding:11px 14px;border-radius:8px;font-weight:bold}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px;border-bottom:1px solid #edf0f5;text-align:left;font-size:13px}.success{background:#ebf9f1;color:#176a3c;padding:10px;border-radius:8px;margin-bottom:14px}@media(max-width:820px){.grid{grid-template-columns:1fr}}</style></head>
<body><header class="top"><strong>SPS CRM · Quotations</strong><a href="dashboard.php">← Dashboard</a></header>
<main class="wrap"><?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?>
<div class="grid"><div class="card"><h2>Create Quotation</h2><form method="post"><?=csrf_field()?>
<div class="field"><label>Client</label><select name="client_id" required><option value="">Select client</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>"><?=e($c['first_name'].' '.$c['last_name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Description</label><input name="description" required></div>
<div class="field"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" required></div>
<div class="field"><label>Valid Until</label><input name="valid_until" type="date"></div>
<div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
<button class="btn">Create Draft Quotation</button></form></div>
<div class="card"><h2>Quotation List</h2><table class="table"><tr><th>Number</th><th>Client</th><th>Status</th><th>Total</th><th>Issue</th><th>Valid Until</th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['quotation_number'])?></td><td><?=e($r['client_name'])?></td><td><?=e($r['status'])?></td><td>$<?=money($r['total_amount'])?></td><td><?=e($r['issue_date'])?></td><td><?=e($r['valid_until']?:'—')?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6">No quotations yet.</td></tr><?php endif;?></table></div></div></main></body></html>
