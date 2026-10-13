<?php
namespace App\Controllers;
use App\Core\{Auth,View,Database};
final class HealthController { public function index():void{Auth::requireLogin();$pdo=Database::pdo();$checks=['database'=>true,'users'=>(bool)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),'services'=>(bool)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn(),'workflow_versions'=>(bool)$pdo->query('SELECT COUNT(*) FROM workflow_versions')->fetchColumn(),'client_table'=>(bool)$pdo->query('SELECT 1 FROM clients LIMIT 1')->fetch()];View::render('admin/health',['checks'=>$checks]);} }
