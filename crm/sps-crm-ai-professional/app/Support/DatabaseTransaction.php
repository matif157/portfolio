<?php
namespace App\Support;
final class DatabaseTransaction { public static function run(callable $fn):mixed{$pdo=Database::pdo();$pdo->beginTransaction();try{$r=$fn($pdo);$pdo->commit();return $r;}catch(\Throwable $e){$pdo->rollBack();throw $e;}} }
