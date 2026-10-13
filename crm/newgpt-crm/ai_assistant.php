<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!table_exists($pdo,'ai_activity'))redirect('upgrade_enterprise.php');
$q=trim((string)($_GET['q']??$_POST['q']??''));
$mode=$_GET['mode']??'assistant';$answer='';$results=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $input=post_string('q');$q=$input;
 if($q!==''){
  $like='%'.$q.'%';
  $st=$pdo->prepare("SELECT id,client_code,first_name,last_name,email,phone FROM clients WHERE deleted_at IS NULL AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR client_code LIKE ?) ORDER BY id DESC LIMIT 20");
  $st->execute([$like,$like,$like,$like,$like]);$results=$st->fetchAll();
  $answer='Local CRM search completed. Results are limited by the records your current account can access.';
  $st=$pdo->prepare('INSERT INTO ai_activity(user_id,action,input_text,output_text,provider,status) VALUES(?,?,?,?,?,?)');
  $st->execute([current_user()['id'],'crm_search',$q,$answer,'local-crm','success']);
 }
}
$shell_title='AI Assistant';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI Assistant · SPS CRM</title><link rel="stylesheet" href="css/sps-shell.css"><style>body{margin:0;background:#06101d;color:#edf6ff;font-family:Arial}.page{max-width:1200px;margin:auto;padding:22px}.hero{padding:30px;border:1px solid #52e7ff25;border-radius:20px;background:radial-gradient(circle at 15% 20%,#52e7ff14,transparent 35%),linear-gradient(145deg,#102742d9,#05111ff2);box-shadow:0 30px 90px #0006}.hero h1{font-size:44px;margin:0}.muted{color:#7892aa}.search{display:flex;gap:8px;margin-top:20px}.search input{flex:1;padding:14px;border-radius:12px;border:1px solid #ffffff18;background:#ffffff08;color:#fff}.btn{background:#315ee7;color:#fff;border:1px solid #6ea8ff55;border-radius:11px;padding:12px 17px;font-weight:800}.grid{display:grid;grid-template-columns:1.3fr .7fr;gap:16px;margin-top:16px}.card{background:#102742bb;border:1px solid #ffffff15;border-radius:18px;padding:20px}.result{padding:13px;border-bottom:1px solid #ffffff10}.tag{font-size:10px;letter-spacing:.1em;color:#66e8ff;text-transform:uppercase}@media(max-width:800px){.grid{grid-template-columns:1fr}.hero h1{font-size:34px}}</style></head><body class="sps-shell-page"><aside class="sps-sidebar"><div class="sps-sidebar-brand"><div class="sps-logo-orb">S</div><div class="sps-brand-text"><strong>SPS CRM</strong><span>2050 OPERATIONS CORE</span></div><button class="sps-sidebar-toggle" id="spsSidebarToggle">☰</button></div><?php include __DIR__.'/sidebar_nav.php';?></aside><div class="sps-shell-main"><div class="sps-shell-topbar"><div class="sps-breadcrumbs">SPS CRM / AI Assistant</div><button id="spsMobileMenu" class="sps-sidebar-mobile-toggle">☰</button></div><main class="page"><section class="hero"><div class="tag">Local intelligence layer</div><h1>CRM Intelligence</h1><p class="muted">Search, summarize and prioritize using verified CRM data. No pretend external AI provider is enabled.</p><form method="post" class="search"><?=csrf_field()?><input name="q" value="<?=e($q)?>" placeholder="Search clients, emails, phones or client codes"><button class="btn">Search CRM</button></form></section><div class="grid"><section class="card"><h2>Results</h2><?php if($answer):?><p><?=e($answer)?></p><?php endif;?><?php foreach($results as $r):?><div class="result"><strong><?=e($r['first_name'].' '.$r['last_name'])?></strong><div class="muted"><?=e($r['client_code'])?> · <?=e($r['email']?:'No email')?> · <?=e($r['phone']?:'No phone')?></div></div><?php endforeach;?><?php if($q!==''&&!$results):?><p class="muted">No matching CRM records.</p><?php endif;?></section><section class="card"><h2>Safe AI Boundary</h2><p class="muted">This layer can assist with CRM search and structured recommendations. It does not represent legal, tax, accounting or immigration professional judgment.</p></section></div></main></div><script>document.addEventListener('DOMContentLoaded',()=>{const s=document.querySelector('.sps-sidebar'),t=document.getElementById('spsSidebarToggle'),m=document.getElementById('spsMobileMenu');if(t)t.onclick=()=>s.classList.toggle('is-collapsed');if(m)m.onclick=()=>s.classList.toggle('open')});</script></body></html>
