<?php
namespace App\Jobs;
use App\Support\Database;
final class OverdueInvoiceJob{public function handle():int{return Database::pdo()->exec("UPDATE invoices SET status='overdue',updated_at=NOW() WHERE balance>0 AND due_date<CURDATE() AND status IN ('issued','sent','partially_paid')");}}
