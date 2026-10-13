<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !(int)$user['is_active']) {
            return false;
        }
        if (!password_verify($password, $user['password'])) {
            return false;
        }

        Session::regenerate();
        unset($user['password']);
        Session::set('user', $user);
        Session::set('user_id', (int)$user['id']);

        // Load permissions
        $perms = $pdo->prepare(
            'SELECT p.permission_key FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $perms->execute([(int)$user['role_id']]);
        Session::set('permissions', $perms->fetchAll(\PDO::FETCH_COLUMN));

        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);

        // Audit
        self::audit('login', 'Auth', 'User logged in');

        return true;
    }

    public static function logout(): void
    {
        self::audit('logout', 'Auth', 'User logged out');
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int)$id : null;
    }

    public static function can(string $permission): bool
    {
        $perms = Session::get('permissions', []);
        if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
            return true;
        }
        // Super admin role_id = 1
        $user = self::user();
        return $user && (int)($user['role_id'] ?? 0) === 1;
    }

    public static function audit(string $action, string $module, string $description = '', ?string $entityType = null, ?int $entityId = null, $old = null, $new = null): void
    {
        try {
            $pdo = Database::pdo();
            $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, module, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                self::id(),
                $action,
                $module,
                $entityType,
                $entityId,
                $description,
                $old !== null ? json_encode($old) : null,
                $new !== null ? json_encode($new) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            // never break app because of audit
        }
    }
}
