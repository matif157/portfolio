<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Employee
{
    /** @return array<int,array<string,mixed>> */
    public static function all(string $search = '', int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT * FROM employees';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE name LIKE :q OR device_user_id LIKE :q OR card_no LIKE :q OR department LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY name LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(string $search = ''): int
    {
        $sql = 'SELECT COUNT(*) FROM employees';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE name LIKE :q OR device_user_id LIKE :q OR card_no LIKE :q OR department LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM employees WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByDeviceId(string $deviceUserId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM employees WHERE device_user_id = :u');
        $stmt->execute([':u' => $deviceUserId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO employees
                (device_user_id, name, department, designation, card_no, role, fingerprint_enrolled)
             VALUES
                (:u, :n, :d, :des, :c, :r, :fp)'
        );
        $stmt->execute([
            ':u'   => $data['device_user_id'],
            ':n'   => $data['name'],
            ':d'   => $data['department']  ?? null,
            ':des' => $data['designation'] ?? null,
            ':c'   => $data['card_no']     ?? null,
            ':r'   => (int)($data['role']  ?? 0),
            ':fp'  => (int)($data['fingerprint_enrolled'] ?? 0),
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE employees SET
                device_user_id = :u, name = :n, department = :d, designation = :des,
                card_no = :c, role = :r, fingerprint_enrolled = :fp
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'  => $id,
            ':u'   => $data['device_user_id'],
            ':n'   => $data['name'],
            ':d'   => $data['department']  ?? null,
            ':des' => $data['designation'] ?? null,
            ':c'   => $data['card_no']     ?? null,
            ':r'   => (int)($data['role']  ?? 0),
            ':fp'  => (int)($data['fingerprint_enrolled'] ?? 0),
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM employees WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function upsertFromDevice(array $devUser): int
    {
        $existing = self::findByDeviceId((string)$devUser['user_id']);
        $data = [
            'device_user_id' => (string)$devUser['user_id'],
            'name'           => $devUser['name'] !== '' ? $devUser['name'] : ('User ' . $devUser['user_id']),
            'role'           => (int)($devUser['role'] ?? 0),
            'card_no'        => $devUser['card_no'] ? (string)$devUser['card_no'] : null,
        ];
        if ($existing) {
            self::update((int)$existing['id'], array_merge($existing, $data));
            return (int)$existing['id'];
        }
        return self::create($data);
    }
}
