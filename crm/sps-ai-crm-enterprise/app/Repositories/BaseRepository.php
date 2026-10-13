<?php
namespace App\Repositories;
use App\Support\Database;use App\Support\TenantContext;
abstract class BaseRepository { public function __construct(protected string $table){} public function find(int $id):?array{$s=Database::pdo()->prepare("SELECT * FROM {$this->table} WHERE tenant_id=? AND id=? LIMIT 1");$s->execute([TenantContext::id(),$id]);$r=$s->fetch();return $r?:null;} public function all(int $limit=100):array{$s=Database::pdo()->prepare("SELECT * FROM {$this->table} WHERE tenant_id=? ORDER BY id DESC LIMIT {$limit}");$s->execute([TenantContext::id()]);return $s->fetchAll();}}
