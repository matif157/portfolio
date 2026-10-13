<?php
namespace App\Services; use App\Core\Database; final class BookkeepingService { public function statusNotes(int $limit=100):array{$s=Database::pdo()->prepare('SELECT * FROM bookkeeping_status_notes ORDER BY created_at DESC LIMIT ?');$s->bindValue(1,$limit,\PDO::PARAM_INT);$s->execute();return $s->fetchAll();}}
