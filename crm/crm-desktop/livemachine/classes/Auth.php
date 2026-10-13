<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Auth
{
    private const SESSION_TTL = 3600; // 1h

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        if (isset($_SESSION['user_id'], $_SESSION['last_seen'])) {
            if (time() - $_SESSION['last_seen'] > self::SESSION_TTL) {
                self::logout();
            } else {
                $_SESSION['last_seen'] = time();
            }
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, password_hash, name, role FROM users WHERE username = :u LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$row['id'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['last_seen'] = time();
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        self::start();
        if (!self::check()) {
            require_once __DIR__ . '/../config/paths.php';
            header('Location: ' . BASE_URL . '/index.php?route=login');
            exit;
        }
    }

    public static function requireApi(): void
    {
        self::start();
        if (!self::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'unauthorized']);
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function csrf(): string
    {
        self::start();
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
    }

    public static function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::verifyCsrf($token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'csrf_failed']);
            exit;
        }
    }

    public static function user(): array
    {
        return [
            'id'   => $_SESSION['user_id']   ?? 0,
            'name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
        ];
    }
}
