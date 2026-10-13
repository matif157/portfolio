<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!phase3_ready($pdo)) redirect('upgrade_phase3.php');

$uploadDir=__DIR__.'/uploads/bank_statements';
if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);

function ensure_statement_periods(PDO $pdo,int $accountId,int $year): void {
    $st=$pdo->prepare('SELECT id FROM bank_statement_periods WHERE bank_account_id=? AND statement_year=?');
    $st->execute([$accountId,$year]);
    if($st->fetchColumn()) return;
    $ins=$pdo->prepare('INSERT INTO bank_statement_periods(bank_account_id,statement_year,statement_month,status) VALUES(?,?,?, "missing")');
    for($m=1;$m<=12;$m++) $ins->execute([$accountId,$year,$m]);
}

$msg=flash('success'); $error=null;
$clientId=(int)($_GET['client_id']??$_POST['client_id']??0);
$year=(int)($_GET['year']??$_POST['year']??date('Y'));
if($year<2000||$year>2100)$year=(int)date('Y');

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=post_string('action');
    try{
        if($action==='add_account'){
            $clientId=post_int('client_id');$bank=post_string('bank_name');
            if(!$clientId||$bank==='') throw new RuntimeException('Client and bank name are required.');
            $st=$pdo->prepare('INSERT INTO bank_accounts(client_id,business_id,bank_name,account_name,account_last4,account_type) VALUES(?,?,?,?,?,?)');
            $last4=preg_replace('/\D+/','',post_string('account_last4'));
            $last4=$last4!==null?substr($last4,-4):null;
            $st->execute([$clientId,post_int('business_id')?:null,$bank,post_string('account_name')?:null,$last4?:null,post_string('account_type')?:null]);
            $accountId=(int)$pdo->lastInsertId();
            ensure_statement_periods($pdo,$accountId,$year);
            activity($pdo,'create','bank_accounts',$accountId,'Bank account added');
            flash('success','Bank account added and twelve monthly statement periods prepared.');
            redirect('bank_statements.php?client_id='.$clientId.'&year='.$year);
        }

        if($action==='prepare_year'){
            $accountId=post_int('bank_account_id');
            $st=$pdo->prepare('SELECT client_id FROM bank_accounts WHERE id=?');$st->execute([$accountId]);$owner=(int)$st->fetchColumn();
            if(!$owner) throw new RuntimeException('Bank account not found.');
            ensure_statement_periods($pdo,$accountId,$year);
            flash('success','Required statement months prepared for '.$year.'.');
            redirect('bank_statements.php?client_id='.$owner.'&year='.$year);
        }

        if($action==='upload'){
            $accountId=post_int('bank_account_id');$periodId=post_int('period_id');
            $st=$pdo->prepare('SELECT bap.*,ba.client_id,ba.business_id,ba.bank_name,ba.account_last4 FROM bank_statement_periods bap JOIN bank_accounts ba ON ba.id=bap.bank_account_id WHERE bap.id=? AND bap.bank_account_id=?');
            $st->execute([$periodId,$accountId]);$period=$st->fetch();
            if(!$period) throw new RuntimeException('Statement period not found.');
            if(empty($_FILES['statement']['name']) || $_FILES['statement']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Please select a valid statement file.');
            $file=$_FILES['statement'];
            if($file['size']>10*1024*1024) throw new RuntimeException('Maximum file size is 10 MB.');
            $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
            $allowed=['application/pdf','image/jpeg','image/png'];
            if(!in_array($mime,$allowed,true)) throw new RuntimeException('Only PDF, JPG and PNG files are accepted.');
            $extension=match($mime){'application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png',default=>'bin'};
            $stored=bin2hex(random_bytes(16)).'.'.$extension;
            $dest=$uploadDir.'/'.$stored;
            if(!move_uploaded_file($file['tmp_name'],$dest)) throw new RuntimeException('Unable to store uploaded file.');
            $relative='uploads/bank_statements/'.$stored;
            $pdo->beginTransaction();
            try{
                $pdo->prepare('UPDATE bank_statement_periods SET status="uploaded",file_name=?,file_path=?,file_size=?,mime_type=?,uploaded_at=NOW(),notes=NULL WHERE id=?')
                    ->execute([substr((string)$file['name'],0,255),$relative,(int)$file['size'],$mime,$periodId]);
                $doc=$pdo->prepare('INSERT INTO documents(client_id,business_id,bank_statement_period_id,document_type,original_name,stored_name,file_path,mime_type,file_size,status,uploaded_by) VALUES(?,?,?,?,?,?,?,?,?,"uploaded",?)');
                $doc->execute([(int)$period['client_id'],$period['business_id'],$periodId,'bank_statement',substr((string)$file['name'],0,255),$stored,$relative,$mime,(int)$file['size'],(int)current_user()['id']]);
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();@unlink($dest);throw $e;}
            activity($pdo,'upload','bank_statements',$periodId,'Bank statement uploaded for '.$period['statement_year'].'-'.$period['statement_month']);
            notify_user($pdo,(int)current_user()['id'],(int)$period['client_id'],'bank_statement','Bank statement received','A bank statement was uploaded for '.$period['statement_year'].'-'.str_pad((string)$period['statement_month'],2,'0',STR_PAD_LEFT).'.');
            flash('success','Statement uploaded and matched to the correct month.');
            redirect('bank_statements.php?client_id='.(int)$period['client_id'].'&year='.$year);
        }
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('[SPS BANK STATEMENTS] '.$e->getMessage());
        $error=$e->getMessage();
    }
}

$clients=$pdo->query("SELECT id,first_name,last_name FROM clients WHERE deleted_at IS NULL ORDER BY last_name,first_name")->fetchAll();
$businesses=[];
if($clientId){
    $st=$pdo->prepare('SELECT id,business_name FROM businesses WHERE client_id=? ORDER BY business_name');$st->execute([$clientId]);$businesses=$st->fetchAll();
}
$accounts=[];
if($clientId){
    $st=$pdo->prepare('SELECT * FROM bank_accounts WHERE client_id=? AND active=1 ORDER BY id DESC');$st->execute([$clientId]);$accounts=$st->fetchAll();
    foreach($accounts as $a) ensure_statement_periods($pdo,(int)$a['id'],$year);
}
$selectedAccount=(int)($_GET['account_id']??0);
$periods=[];
if($selectedAccount){
    $st=$pdo->prepare('SELECT * FROM bank_statement_periods WHERE bank_account_id=? AND statement_year=? ORDER BY statement_month');$st->execute([$selectedAccount,$year]);$periods=$st->fetchAll();
}
$missing=0;$uploaded=0;$validated=0;
foreach($periods as $p){if($p['status']==='missing')$missing++;elseif($p['status']==='uploaded')$uploaded++;elseif($p['status']==='validated')$validated++;}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bank Statements · SPS CRM</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1200px;margin:auto;padding:28px}.grid{display:grid;grid-template-columns:1fr 1.3fr;gap:16px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px;margin-bottom:16px}.field{margin:10px 0}.field label{display:block;font-size:12px;font-weight:800;margin-bottom:6px}.field input,.field select{width:100%;padding:10px;border:1px solid #d8dfeb;border-radius:8px}.btn{background:#315ee7;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:800;cursor:pointer}.btn.secondary{background:#fff;color:#315ee7;border:1px solid #d8dfeb}.success{background:#ebf9f1;color:#176a3c;padding:10px;border-radius:8px;margin-bottom:14px}.error{background:#fff0f0;color:#a32c2c;padding:10px;border-radius:8px;margin-bottom:14px}.account{padding:12px;border:1px solid #e3e8f0;border-radius:10px;margin:8px 0;text-decoration:none;color:#172033;display:block}.account.active{border-color:#8aa4f3;background:#f3f6ff}.months{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.month{border:1px solid #e3e8f0;border-radius:12px;padding:13px}.missing{background:#fff3f3;border-color:#f0b8b8}.uploaded{background:#eef8ff;border-color:#afd4ee}.validated{background:#ebf9f1;border-color:#b6dfc5}.status{font-size:11px;font-weight:800;text-transform:uppercase}.upload{margin-top:9px}.small{font-size:12px;color:#6e788a}@media(max-width:850px){.grid{grid-template-columns:1fr}.months{grid-template-columns:repeat(2,1fr)}}</style></head>
<body><header class="top"><strong>SPS CRM · Bank Statement Automation</strong><a href="dashboard.php">← Dashboard</a></header><main class="wrap">
<?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?><?php if($error):?><div class="error"><?=e($error)?></div><?php endif;?>
<div class="grid">
<section>
<div class="card"><h2>Client & Bank Account</h2><form method="get"><div class="field"><label>Client</label><select name="client_id" onchange="this.form.submit()"><option value="">Select client</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>" <?=$clientId===$c['id']?'selected':''?>><?=e($c['first_name'].' '.$c['last_name'])?></option><?php endforeach;?></select></div><div class="field"><label>Year</label><input type="number" name="year" min="2000" max="2100" value="<?=e($year)?>"></div></form>
<?php if($clientId):?><h3>Accounts</h3><?php foreach($accounts as $a):?><a class="account <?=$selectedAccount===$a['id']?'active':''?>" href="bank_statements.php?client_id=<?=$clientId?>&year=<?=$year?>&account_id=<?=$a['id']?>"><strong><?=e($a['bank_name'])?></strong><div class="small"><?=e($a['account_name']?:'Account')?> · ending <?=e($a['account_last4']?:'----')?></div></a><?php endforeach;?>
<div style="margin-top:18px"><h3>Add Bank Account</h3><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="add_account"><input type="hidden" name="client_id" value="<?=$clientId?>"><input type="hidden" name="year" value="<?=$year?>">
<div class="field"><label>Bank Name</label><input name="bank_name" required></div><div class="field"><label>Account Name</label><input name="account_name"></div><div class="field"><label>Last 4 Digits</label><input name="account_last4" inputmode="numeric" maxlength="4"></div><div class="field"><label>Account Type</label><input name="account_type" placeholder="Checking / Savings"></div><button class="btn">Add Account</button></form></div><?php endif;?>
</div></section>
<section>
<div class="card"><h2>Monthly Statement Checklist · <?=e($year)?></h2>
<?php if($selectedAccount && $periods):?><div class="months"><?php foreach($periods as $p):$name=date('F',mktime(0,0,0,(int)$p['statement_month'],1));?><div class="month <?=e($p['status'])?>"><strong><?=e($name)?></strong><div class="status"><?=e($p['status'])?></div><?php if($p['file_name']):?><div class="small"><?=e($p['file_name'])?></div><?php endif;?><?php if($p['status']==='missing'):?><form class="upload" method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="upload"><input type="hidden" name="bank_account_id" value="<?=$selectedAccount?>"><input type="hidden" name="period_id" value="<?=$p['id']?>"><input type="hidden" name="year" value="<?=$year?>"><input type="file" name="statement" accept=".pdf,.jpg,.jpeg,.png" required><button class="btn" style="margin-top:7px">Upload</button></form><?php endif;?></div><?php endforeach;?></div><div style="margin-top:18px"><strong>Summary:</strong> <?=e($missing)?> missing · <?=e($uploaded)?> uploaded · <?=e($validated)?> validated</div>
<?php elseif($clientId && $accounts):?><p>Select a bank account to view its monthly checklist.</p>
<?php else:?><p class="small">Select a client and create/select a bank account. Phase 3 prepares all 12 months automatically for the selected year.</p><?php endif;?>
</div>
</section></div>
</main></body></html>
