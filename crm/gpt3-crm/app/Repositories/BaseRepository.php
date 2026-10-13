<?php
namespace App\Repositories; use App\Core\Database;
abstract class BaseRepository{protected string $table;public function find(int $id):?array{$s=Database::pdo()->prepare("SELECT * FROM {$this->table} WHERE id=? LIMIT 1");$s->execute([$id]);$r=$s->fetch();return $r?:null;}public function all(int $limit=200):array{$limit=max(1,min(1000,$limit));$s=Database::pdo()->query("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT {$limit}");return $s->fetchAll();}}
