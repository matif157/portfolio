<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();$message=flash('success');$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $sql=file_get_contents(__DIR__.'/upgrade_phase4.sql');
        if($sql===false) throw new RuntimeException('Migration file missing.');
        $sql=preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*/i','',$sql,1);
        $parts=preg_split('/;\s*(?:\r?\n|$)/',$sql);
        foreach($parts as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}
        activity($pdo,'upgrade','system',null,'Phase 4 database upgrade applied');
        flash('success','Phase 4 database upgrade completed successfully.');
        redirect('upgrade_phase4.php');
    }catch(Throwable $e){
        error_log('[SPS CRM PHASE 4] '.$e->getMessage());
        $error='Phase 4 could not be installed. Make sure MySQL is running and Phase 3 has already been installed.';
    }
}
$ready=true;
foreach(['payments','document_requirements','client_document_requirements','custom_fields','custom_field_values','secure_data','service_messages'] as $t){if(!table_exists($pdo,$t)){$ready=false;break;}}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Phase 4 Setup · SPS CRM</title><style>body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.box{max-width:760px;margin:60px auto;background:#fff;border:1px solid #e4e9f1;border-radius:18px;padding:30px}.note{background:#f4f7ff;padding:14px;border-radius:10px;line-height:1.55}.ok{color:#16804a;font-weight:800}.bad{color:#a22f2f;font-weight:800}.btn{background:#315ee7;color:#fff;border:0;padding:12px 16px;border-radius:9px;font-weight:800;cursor:pointer}.back{display:inline-block;margin-top:18px;color:#315ee7;text-decoration:none}</style></head><body><div class="box"><h1>Phase 4 Database Setup</h1><p class="note">This is an additive migration. Existing CRM data is not intentionally deleted or reset.</p><?php if($message):?><p class="ok"><?=e($message)?></p><?php endif;?><?php if($error):?><p class="bad"><?=e($error)?></p><?php endif;?><p>Status: <span class="<?= $ready?'ok':'bad'?>"><?=$ready?'READY':'UPGRADE REQUIRED'?></span></p><?php if(!$ready):?><form method="post"><?=csrf_field()?><button class="btn">Apply Phase 4 Database Upgrade</button></form><?php endif;?><a class="back" href="dashboard.php">← Dashboard</a></div></body></html>
