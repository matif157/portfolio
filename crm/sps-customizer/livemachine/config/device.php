<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Loads/updates device_config row.
 * The device password (comm_key) is stored in DB so admins can change it from the UI.
 */
final class DeviceConfig
{
    public int $id;
    public string $name;
    public string $ip;
    public int $port;
    public int $password;
    public string $brand;
    public ?string $lastSync;

    public static function load(): self
    {
        $row = Database::pdo()
            ->query('SELECT * FROM device_config ORDER BY id LIMIT 1')
            ->fetch();

        if (!$row) {
            throw new RuntimeException('No device_config row found. Re-import database.sql.');
        }

        $self = new self();
        $self->id       = (int)$row['id'];
        $self->name     = (string)$row['name'];
        $self->ip       = (string)$row['ip'];
        $self->port     = (int)$row['port'];
        $self->password = (int)$row['password'];
        $self->brand    = (string)$row['brand'];
        $self->lastSync = $row['last_sync'];
        return $self;
    }

    public function save(): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE device_config
                SET name = :n, ip = :i, port = :p, password = :pw, brand = :b
              WHERE id = :id'
        );
        $stmt->execute([
            ':n'  => $this->name,
            ':i'  => $this->ip,
            ':p'  => $this->port,
            ':pw' => $this->password,
            ':b'  => $this->brand,
            ':id' => $this->id,
        ]);
    }

    public function markSynced(): void
    {
        Database::pdo()
            ->prepare('UPDATE device_config SET last_sync = NOW() WHERE id = :id')
            ->execute([':id' => $this->id]);
    }
}
