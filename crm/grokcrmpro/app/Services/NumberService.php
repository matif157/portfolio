<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class NumberService
{
    public static function next(string $key): string
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM number_sequences WHERE sequence_key = ? FOR UPDATE');
            $stmt->execute([$key]);
            $seq = $stmt->fetch();
            if (!$seq) {
                $pdo->prepare('INSERT INTO number_sequences (sequence_key, prefix, next_number) VALUES (?,?,1)')
                    ->execute([$key, strtoupper(substr($key, 0, 3)), 1]);
                $seq = ['prefix' => strtoupper(substr($key, 0, 3)), 'next_number' => 1, 'number_length' => 4];
            }
            $num = (int)$seq['next_number'];
            $pdo->prepare('UPDATE number_sequences SET next_number = next_number + 1 WHERE sequence_key = ?')
                ->execute([$key]);
            $pdo->commit();
            return $seq['prefix'] . '-' . str_pad((string)$num, (int)$seq['number_length'], '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
