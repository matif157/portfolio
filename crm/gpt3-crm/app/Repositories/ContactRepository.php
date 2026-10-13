<?php
namespace App\Repositories;
use App\Core\Database;
final class ContactRepository {
    public function all(int $limit=100): array { $s=Database::pdo()->prepare('SELECT * FROM contacts WHERE tenant_id=1 ORDER BY id DESC LIMIT ?'); $s->bindValue(1,$limit,\PDO::PARAM_INT); $s->execute(); return $s->fetchAll(); }
    public function find(int $id): ?array { $s=Database::pdo()->prepare('SELECT * FROM contacts WHERE tenant_id=1 AND id=? LIMIT 1'); $s->execute([$id]); $r=$s->fetch(); return $r?:null; }
 public function primaryPhone(int $clientId):?string{ $s=Database::pdo()->prepare("SELECT phone FROM contacts WHERE client_id=? AND phone IS NOT NULL AND phone<>'' ORDER BY is_primary DESC,id ASC LIMIT 1");$s->execute([$clientId]);$v=$s->fetchColumn();return $v?:null;}
}

