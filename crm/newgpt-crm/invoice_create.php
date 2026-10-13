<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!table_exists($pdo,'invoices')) redirect('upgrade_phase5.php');

$shell_title='Create Invoice';
$msg=flash('success');
$error=null;
$clients=$pdo->query("SELECT id,first_name,last_name,email FROM clients WHERE deleted_at IS NULL ORDER BY last_name,first_name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $clientId=post_int('client_id');
    $issueDate=post_string('issue_date',date('Y-m-d'));
    $dueDate=post_string('due_date');
    $descs=$_POST['item_description']??[];
    $qtys=$_POST['item_quantity']??[];
    $prices=$_POST['item_unit_price']??[];
    $tax=(float)($_POST['tax_amount']??0);

    if(!is_array($descs)||!is_array($qtys)||!is_array($prices)){$error='Invalid invoice line items.';}
    elseif(!$clientId){$error='Select a client.';}
    else{
        $items=[];
        $subtotal=0;
        foreach($descs as $i=>$desc){
            $desc=is_string($desc)?trim($desc):'';
            $qty=(float)($qtys[$i]??0);
            $price=(float)($prices[$i]??0);
            if($desc==='' && $qty===0 && $price===0)continue;
            if($desc===''||$qty<=0||$price<0){$error='Each invoice line needs a description, positive quantity and valid price.';break;}
            $line=$qty*$price;
            $items[]=['description'=>$desc,'quantity'=>$qty,'unit_price'=>$price,'line_total'=>$line];
            $subtotal+=$line;
        }
        if(!$items && !$error)$error='Add at least one invoice line.';
        if($tax<0&&!$error)$error='Tax cannot be negative.';
        if(!$error){
            try{
                $pdo->beginTransaction();
                $number='INV-'.date('YmdHis').'-'.random_int(100,999);
                $total=$subtotal+$tax;
                $st=$pdo->prepare('INSERT INTO invoices(client_id,invoice_number,status,issue_date,due_date,subtotal,tax_amount,total_amount,paid_amount,notes) VALUES(?,?,?,?,?,?,?, ?,0,?)');
                $st->execute([$clientId,$number,'draft',$issueDate,$dueDate?:null,$subtotal,$tax,$total,post_string('notes')?:null]);
                $invoiceId=(int)$pdo->lastInsertId();
                $st=$pdo->prepare('INSERT INTO invoice_items(invoice_id,description,quantity,unit_price,line_total) VALUES(?,?,?,?,?)');
                foreach($items as $item)$st->execute([$invoiceId,$item['description'],$item['quantity'],$item['unit_price'],$item['line_total']]);
                $pdo->commit();
                activity($pdo,'create','invoices',$invoiceId,'Invoice created: '.$number);
                flash('success','Invoice '.$number.' created successfully.');
                redirect('invoice_view.php?id='.$invoiceId);
            }catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                error_log('[SPS INVOICE CREATE] '.$e->getMessage());
                $error='Invoice could not be saved. Please try again.';
            }
        }
    }
}
$defaultDueDate=date('Y-m-d',strtotime('+30 days'));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Invoice · SPS CRM</title><link rel="stylesheet" href="assets/css/future-theme.css"><link rel="stylesheet" href="assets/css/sps-shell.css"><script defer src="assets/js/future-ui.js"></script><script defer src="assets/js/sps-shell.js"></script>
<style>.invoice-form{max-width:1120px;margin:0 auto;padding:22px}.layout{display:grid;grid-template-columns:1.6fr .7fr;gap:16px}.line{display:grid;grid-template-columns:2fr .7fr .9fr auto;gap:8px;align-items:center;margin:9px 0}.field{margin:11px 0}.field label{display:block;font-size:11px;font-weight:800;color:#91a7bd;margin-bottom:6px;text-transform:uppercase;letter-spacing:.08em}.summary{position:sticky;top:95px}.total-line{display:flex;justify-content:space-between;padding:9px 0;color:#a7bed1}.total-line.total{font-size:24px;font-weight:900;color:#fff;border-top:1px solid rgba(147,197,253,.12);margin-top:7px;padding-top:15px}.remove{width:38px;height:38px}.add-line{margin-top:10px}.error{margin-bottom:14px}@media(max-width:900px){.layout{grid-template-columns:1fr}.summary{position:static}.line{grid-template-columns:1fr 1fr 1fr auto}}</style></head>
<body class="module-invoice-create sps-shell-page"><?php include __DIR__.'/sidebar.php'; ?><div class="sps-shell-main"><?php include __DIR__.'/shell_topbar.php'; ?><main class="invoice-form">
<div style="display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:18px"><div><h1 style="margin:0">Create Invoice</h1><div class="muted">Build a clean, itemized SPS billing document.</div></div><a class="btn secondary" href="invoices.php">← Invoice Center</a></div>
<?php if($error):?><div class="error"><?=e($error)?></div><?php endif;?>
<form method="post" id="invoiceForm"><?=csrf_field()?>
<div class="layout"><section><div class="card"><h2>Invoice Details</h2>
<div class="field"><label>Client</label><select name="client_id" required><option value="">Select client</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>" <?=((int)($_POST['client_id']??0)===$c['id'])?'selected':''?>><?=e($c['first_name'].' '.$c['last_name'].' · '.($c['email']?:'no email'))?></option><?php endforeach;?></select></div>
<div class="line"><strong>Description</strong><strong>Qty</strong><strong>Unit Price</strong><span></span></div>
<div id="lines"><div class="line"><input name="item_description[]" placeholder="Service / item" required><input name="item_quantity[]" type="number" min="0.01" step="0.01" value="1" required><input name="item_unit_price[]" type="number" min="0" step="0.01" value="0" required><button type="button" class="btn danger remove" onclick="removeLine(this)">×</button></div></div>
<button type="button" class="btn secondary add-line" onclick="addLine()">+ Add line</button>
<div class="field"><label>Notes</label><textarea name="notes" rows="4" placeholder="Payment terms, service notes, or client message."><?=old('notes')?></textarea></div>
</div></section>
<aside class="card summary"><h2>Billing</h2><div class="field"><label>Issue Date</label><input name="issue_date" type="date" value="<?=e($_POST['issue_date']??date('Y-m-d'))?>" required></div><div class="field"><label>Due Date</label><input name="due_date" type="date" value="<?=e($_POST['due_date']??$defaultDueDate)?>"></div><div class="field"><label>Tax</label><input id="tax" name="tax_amount" type="number" min="0" step="0.01" value="<?=e($_POST['tax_amount']??0)?>"></div><div class="total-line"><span>Subtotal</span><strong id="subtotal">$0.00</strong></div><div class="total-line"><span>Tax</span><strong id="taxDisplay">$0.00</strong></div><div class="total-line total"><span>Total</span><strong id="total">$0.00</strong></div><button class="btn" style="width:100%;margin-top:15px">Create Draft Invoice</button></aside></div></form>
</main></div>
<script>
function addLine(){const box=document.getElementById('lines'),row=document.createElement('div');row.className='line';row.innerHTML='<input name="item_description[]" placeholder="Service / item" required><input name="item_quantity[]" type="number" min="0.01" step="0.01" value="1" required><input name="item_unit_price[]" type="number" min="0" step="0.01" value="0" required><button type="button" class="btn danger remove" onclick="removeLine(this)">×</button>';box.appendChild(row);bind();}
function removeLine(btn){const rows=document.querySelectorAll('#lines .line');if(rows.length>1){btn.parentElement.remove();calc();}}
function bind(){document.querySelectorAll('#lines input').forEach(x=>x.oninput=calc);document.getElementById('tax').oninput=calc;calc();}
function calc(){let sub=0;document.querySelectorAll('#lines .line').forEach(r=>{const q=parseFloat(r.querySelector('[name="item_quantity[]"]').value)||0,p=parseFloat(r.querySelector('[name="item_unit_price[]"]').value)||0;sub+=q*p;});const tax=parseFloat(document.getElementById('tax').value)||0;document.getElementById('subtotal').textContent='$'+sub.toFixed(2);document.getElementById('taxDisplay').textContent='$'+tax.toFixed(2);document.getElementById('total').textContent='$'+(sub+tax).toFixed(2);}
bind();
</script></body></html>
