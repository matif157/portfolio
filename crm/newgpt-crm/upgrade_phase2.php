<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db(); $message=flash('success'); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $sql=file_get_contents(__DIR__.'/upgrade_phase2.sql');
        if($sql===false) throw new RuntimeException('Migration file missing.');
        $sql=preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*/i','',$sql,1);
        $parts=preg_split('/;\s*(?:\r?\n|$)/',$sql);
        foreach($parts as $s){$s=trim($s);if($s!=='')$pdo->exec($s);}
        activity($pdo,'upgrade','system',null,'Phase 2 database upgrade applied');
        flash('success','Phase 2 database upgrade completed.');
        redirect('upgrade_phase2.php');
    }catch(Throwable $e){error_log('[SPS CRM PHASE 2] '.$e->getMessage());$error='Upgrade failed. Verify MySQL is running and the gpt database is accessible.';}
}
$ready=phase2_ready($pdo);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Phase 2 Setup · SPS CRM</title><style>body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.box{max-width:720px;margin:70px auto;background:#fff;border:1px solid #e4e9f1;border-radius:18px;padding:30px}.btn{background:#315ee7;color:#fff;border:0;padding:12px 16px;border-radius:9px;font-weight:800;cursor:pointer}.note{background:#f4f7ff;padding:13px;border-radius:10px;line-height:1.5}.ok{color:#16804a}.bad{color:#a22f2f}.back{color:#315ee7;text-decoration:none;display:inline-block;margin-top:18px}</style></head><body><div class="box"><h1>Phase 2 Database Setup</h1><p class="note">This migration is additive and does not drop or reset Phase 1 data.</p><?php if($message):?><p class="ok"><?=e($message)?></p><?php endif;?><?php if($error):?><p class="bad"><?=e($error)?></p><?php endif;?><p>Status: <strong class="<?= $ready?'ok':'bad'?>"><?=$ready?'READY':'UPGRADE REQUIRED'?></strong></p><?php if(!$ready):?><form method="post"><?=csrf_field()?><button class="btn">Apply Phase 2 Database Upgrade</button></form><?php endif;?><a class="back" href="dashboard.php">← Dashboard</a></div></body></html>
