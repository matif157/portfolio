<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();$message=flash('success');$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $sql=file_get_contents(__DIR__.'/upgrade_phase5.sql');
        if($sql===false) throw new RuntimeException('Phase 5 migration file missing.');
        $sql=preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*/i','',$sql,1);
        foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){
            $statement=trim($statement);
            if($statement!=='')$pdo->exec($statement);
        }
        activity($pdo,'upgrade','system',null,'Phase 5 database upgrade applied');
        flash('success','Phase 5 database upgrade completed successfully.');
        redirect('upgrade_phase5.php');
    }catch(Throwable $e){
        error_log('[SPS CRM PHASE 5] '.$e->getMessage());
        $error='Phase 5 could not be installed. Make sure Phase 4 is installed and MySQL is running.';
    }
}
$ready=true;
foreach(['quotations','quotation_items','project_comments','task_comments','recurring_project_series','user_preferences','settings'] as $t){
    if(!table_exists($pdo,$t)){$ready=false;break;}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Phase 5 Setup · SPS CRM</title>
<style>body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.box{max-width:760px;margin:60px auto;background:#fff;border:1px solid #e4e9f1;border-radius:18px;padding:30px}.note{background:#f4f7ff;padding:14px;border-radius:10px;line-height:1.55}.ok{color:#16804a;font-weight:800}.bad{color:#a22f2f;font-weight:800}.btn{background:#315ee7;color:#fff;border:0;padding:12px 16px;border-radius:9px;font-weight:800;cursor:pointer}.back{display:inline-block;margin-top:18px;color:#315ee7;text-decoration:none}</style></head>
<body><div class="box"><h1>Phase 5 Database Setup</h1><p class="note">Additive migration only. Existing Phase 1–4 records are not intentionally dropped or reset.</p>
<?php if($message):?><p class="ok"><?=e($message)?></p><?php endif;?><?php if($error):?><p class="bad"><?=e($error)?></p><?php endif;?>
<p>Status: <span class="<?= $ready?'ok':'bad'?>"><?= $ready?'READY':'UPGRADE REQUIRED'?></span></p>
<?php if(!$ready):?><form method="post"><?=csrf_field()?><button class="btn">Apply Phase 5 Database Upgrade</button></form><?php endif;?>
<a class="back" href="dashboard.php">← Dashboard</a></div></body></html>
