<?php
namespace App\Repositories;
use App\Support\Database;use App\Support\TenantContext;
final class AIInteractionRepository { public function query(string $sql,array $params=[]):array{$s=Database::pdo()->prepare($sql);$s->execute($params);return $s->fetchAll();} public function tenantId():int{return TenantContext::id();} }
