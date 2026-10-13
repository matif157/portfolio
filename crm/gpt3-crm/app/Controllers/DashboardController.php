<?php
namespace App\Controllers;
use App\Core\{Auth,Database,View};
final class DashboardController { public function index():void{Auth::requireLogin();$pdo=Database::pdo();$stats=['clients'=>(int)$pdo->query('SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL')->fetchColumn(),'open_projects'=>(int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('open','in_progress')")->fetchColumn(),'upcoming'=>(int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status='upcoming'")->fetchColumn(),'receivable'=>(float)$pdo->query('SELECT COALESCE(SUM(balance),0) FROM invoices')->fetchColumn(),'missing'=>(int)$pdo->query("SELECT COUNT(*) FROM requirement_completions WHERE status IN ('missing','requested')")->fetchColumn()];View::render('dashboard/index',['stats'=>$stats]);}}
