<?php
namespace App\Controllers;
use App\Support\{Auth,Response,Database};
final class ApiController {
 public function dashboard():never{Auth::requireLogin();$pdo=Database::pdo();Response::json(['clients'=>(int)$pdo->query("SELECT COUNT(*) FROM clients WHERE tenant_id=1")->fetchColumn(),'projects'=>(int)$pdo->query("SELECT COUNT(*) FROM projects WHERE tenant_id=1")->fetchColumn(),'balance'=>(float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE tenant_id=1")->fetchColumn()]);}
}
