<?php
/**
 * AR Radius - Auth + session helpers
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cfg = require __DIR__ . '/config.php';
        session_name($cfg['session']['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();

        // Inactivity timeout
        if (isset($_SESSION['last_activity'])
            && (time() - $_SESSION['last_activity']) > $cfg['session']['lifetime']) {
            self::logout();
        }
        $_SESSION['last_activity'] = time();
    }

    public static function login(string $username, string $password): bool
    {
        $row = DB::one(
            'SELECT id, username, password_hash FROM ar_admins WHERE username = ? LIMIT 1',
            [$username]
        );
        if (!$row || !password_verify($password, $row['password_hash'])) {
            self::audit(null, 'login_failed', $username, $_SERVER['REMOTE_ADDR'] ?? null);
            return false;
        }

        self::start();
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int)$row['id'];
        $_SESSION['admin_user'] = $row['username'];
        $_SESSION['csrf']       = bin2hex(random_bytes(32));

        DB::exec('UPDATE ar_admins SET last_login = NOW() WHERE id = ?', [$row['id']]);
        self::audit($row['username'], 'login_success', null, $_SERVER['REMOTE_ADDR'] ?? null);
        return true;
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['admin_id']);
    }

    public static function require(): void
    {
        if (!self::check()) {
            if (self::isApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'unauthorized']);
                exit;
            }
            header('Location: /login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        self::start();
        if (!empty($_SESSION['admin_user'])) {
            self::audit($_SESSION['admin_user'], 'logout', null, $_SERVER['REMOTE_ADDR'] ?? null);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?string
    {
        self::start();
        return $_SESSION['admin_user'] ?? null;
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return !empty($token)
            && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    public static function requireCsrf(): void
    {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::verifyCsrf($token)) {
            http_response_code(403);
            if (self::isApiRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'invalid_csrf']);
            } else {
                echo 'Invalid CSRF token.';
            }
            exit;
        }
    }

    private static function isApiRequest(): bool
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    }

    public static function audit(?string $user, string $action, ?string $target = null,
                                  ?string $ip = null, ?string $details = null): void
    {
        try {
            DB::exec(
                'INSERT INTO ar_audit_log (admin_user, action, target, details, ip_address)
                 VALUES (?, ?, ?, ?, ?)',
                [$user, $action, $target, $details, $ip]
            );
        } catch (Throwable $e) {
            // Don't break flow on audit failure
        }
    }
}
