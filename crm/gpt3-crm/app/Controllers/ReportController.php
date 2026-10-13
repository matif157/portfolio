<?php
namespace App\Controllers;
use App\Core\{Auth,Database,View};
final class ReportController {public function index():void{Auth::requireLogin();$pdo=Database::pdo();$report=['clients_by_type'=>$pdo->query('SELECT client_type,COUNT(*) total FROM clients WHERE deleted_at IS NULL GROUP BY client_type')->fetchAll(),'invoice_status'=>$pdo->query('SELECT status,COUNT(*) total,COALESCE(SUM(grand_total),0) amount FROM invoices GROUP BY status')->fetchAll(),'top_services'=>$pdo->query('SELECT s.name,COUNT(cs.id) total FROM services s LEFT JOIN client_services cs ON cs.service_id=s.id GROUP BY s.id ORDER BY total DESC LIMIT 5')->fetchAll()];View::render('reports/index',$report);}}
