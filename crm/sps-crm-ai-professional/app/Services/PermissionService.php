<?php
namespace App\Services;
use App\Support\Database;use App\Support\Auth;
final class PermissionService {
 public function can(string $permission):bool{
  $u=Auth::user(); if(!$u)return false;
  $s=Database::pdo()->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.code=?");
  $s->execute([$u['role_id'],$permission]);return (bool)$s->fetchColumn();
 }
 public function require(string $permission):void{if(!$this->can($permission)){http_response_code(403);exit('Forbidden');}}
}
