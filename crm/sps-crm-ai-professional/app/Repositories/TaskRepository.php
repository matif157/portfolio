<?php
namespace App\Repositories;
use App\Support\Database;
class TaskRepository {
 protected string $table='tasks';
 public function all(int $tenant=1):array{ $s=Database::pdo()->prepare("SELECT * FROM {$this->table} WHERE tenant_id=? ORDER BY id DESC");$s->execute([$tenant]);return $s->fetchAll(); }
 public function find(int $id):?array{ $s=Database::pdo()->prepare("SELECT * FROM {$this->table} WHERE id=? LIMIT 1");$s->execute([$id]);$r=$s->fetch();return $r?:null; }
 public function delete(int $id):bool{return Database::pdo()->prepare("DELETE FROM {$this->table} WHERE id=?")->execute([$id]);}
}
