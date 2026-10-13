<?php
namespace App\Services\Reporting;
use App\Support\Database;use App\Support\TenantContext;
final class ReportingService { public function activityOverview():array{$pdo=Database::pdo();$s=$pdo->prepare('SELECT module,COUNT(*) total FROM activities WHERE tenant_id=? GROUP BY module ORDER BY total DESC LIMIT 5');$s->execute([TenantContext::id()]);return $s->fetchAll();} public function receivables():array{$s=Database::pdo()->prepare("SELECT status,COUNT(*) count,SUM(balance) balance FROM invoices WHERE tenant_id=? GROUP BY status");$s->execute([TenantContext::id()]);return $s->fetchAll();}}
