<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
$message=flash('success');$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $sql=file_get_contents(__DIR__.'/enterprise_schema.sql');
        if($sql===false) throw new RuntimeException('Enterprise schema missing.');
        $sql=preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*/i','',$sql,1);
        foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}
        activity($pdo,'upgrade','system',null,'Enterprise master schema installed');
        flash('success','Enterprise master schema installed successfully.');
        redirect('upgrade_enterprise.php');
    }catch(Throwable $e){error_log('[SPS ENTERPRISE UPGRADE] '.$e->getMessage());$error='Enterprise upgrade failed. Confirm MySQL is running and the gpt database is selected.';}
}
$tables=['contacts','client_groups','tags','leads','opportunities','products','workflow_templates','appointments','invoice_templates','taxes','journals','security_events','sessions','devices','automation_jobs','automation_triggers','automation_escalations','ai_prompts','ai_activity','ai_recommendations','departments','teams'];
$missing=[];foreach($tables as $t)if(!table_exists($pdo,$t))$missing[]=$t;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enterprise Setup · SPS CRM</title><link rel="stylesheet" href="css/sps-shell.css"><link rel="stylesheet" href="css/invoice-print.css"><style>body{margin:0;background:#06101d;color:#edf6ff;font-family:Arial}.box{max-width:850px;margin:60px auto;background:linear-gradient(145deg,#122942,#071522);border:1px solid #ffffff18;border-radius:20px;padding:30px;box-shadow:0 30px 90px #0008}.note{background:#52e7ff12;border:1px solid #52e7ff26;padding:14px;border-radius:12px}.ok{color:#72f1bd}.bad{color:#ff9aaa}.btn{background:#315ee7;color:#fff;border:1px solid #6ea8ff55;padding:12px 16px;border-radius:10px;font-weight:800}.grid{display:flex;flex-wrap:wrap;gap:7px;margin:15px 0}.tag{padding:5px 8px;border-radius:99px;background:#ffffff08;border:1px solid #ffffff12;color:#9bb4cc;font-size:11px}</style></head><body><div class="box"><h1>Enterprise Master Setup</h1><p class="note">This is an additive database migration. It expands the current gpt database to cover the master enterprise module tree.</p><?php if($message):?><p class="ok"><?=e($message)?></p><?php endif;?><?php if($error):?><p class="bad"><?=e($error)?></p><?php endif;?><p><strong><?=count($missing)?'Upgrade required':'Enterprise schema ready'?></strong></p><div class="grid"><?php foreach($missing as $m):?><span class="tag"><?=e($m)?></span><?php endforeach;?></div><?php if($missing):?><form method="post"><?=csrf_field()?><button class="btn">Install Enterprise Schema</button></form><?php endif;?><p><a href="dashboard.php">← Dashboard</a></p></div></body></html>
