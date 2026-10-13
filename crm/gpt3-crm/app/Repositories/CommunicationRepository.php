<?php
namespace App\Repositories;
use App\Core\Database;
final class CommunicationRepository {
    public function all(int $limit=100): array { $s=Database::pdo()->prepare('SELECT * FROM communications WHERE tenant_id=1 ORDER BY id DESC LIMIT ?'); $s->bindValue(1,$limit,\PDO::PARAM_INT); $s->execute(); return $s->fetchAll(); }
    public function find(int $id): ?array { $s=Database::pdo()->prepare('SELECT * FROM communications WHERE tenant_id=1 AND id=? LIMIT 1'); $s->execute([$id]); $r=$s->fetch(); return $r?:null; }
}
