<?php
namespace App\Services;
use App\Core\Auth; use App\Core\Database;
final class PermissionService {
    public function can(string $module,string $action): bool {
        $u=Auth::user(); if(!$u)return false; if(($u['role_name']??'')==='Super Admin')return true;
        $s=Database::pdo()->prepare('SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.module_name=? AND p.permission_key=? LIMIT 1');
        $s->execute([$u['role_id'],$module,$action]); return (bool)$s->fetchColumn();
    }
    public function require(string $module,string $action): void { if(!$this->can($module,$action)){http_response_code(403); echo 'Forbidden'; exit;} }
}
