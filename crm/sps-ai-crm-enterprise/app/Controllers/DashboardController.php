<?php
namespace App\Controllers;
use App\Support\{Auth,Database,View};
final class DashboardController {
 public function index():void{Auth::requireLogin();$pdo=Database::pdo();
  $stats=[];
  foreach(['clients'=>'SELECT COUNT(*) FROM clients WHERE tenant_id=1','open_projects'=>"SELECT COUNT(*) FROM projects WHERE tenant_id=1 AND status='open'","upcoming_projects"=>"SELECT COUNT(*) FROM projects WHERE tenant_id=1 AND status='upcoming'","receivable"=>"SELECT COALESCE(SUM(balance),0) FROM invoices WHERE tenant_id=1 AND balance>0"] as $k=>$q)$stats[$k]=$pdo->query($q)->fetchColumn();
  View::render('dashboard/index',['stats'=>$stats]);
 }
}
