<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Session login. Users are created only by the admin; there is no signup. */
final class Auth
{
    public const MAX_FAILURES = 5;
    public const LOCKOUT_MINUTES = 15;

    private static ?array $current = null;

    /** Returns null on success, or an error message to show. */
    public static function attempt(PDO $db, string $username, string $password, string $ip): ?string
    {
        $username = trim($username);
        if (self::isLockedOut($db, $username, $ip)) {
            return 'Too many failed attempts. Try again in ' . self::LOCKOUT_MINUTES . ' minutes.';
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verify against a dummy hash when the user doesn't exist, so timing doesn't reveal usernames.
        $hash = $user['password_hash'] ?? '$2y$10$EepLBHdog4RtM/CHwfWD.ej5dWwshN5du0S2Y9gsYXQYXoA1wikgu';
        $ok = password_verify($password, $hash) && $user && (int) $user['is_active'] === 1;

        if (!$ok) {
            $db->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, ?)')
                ->execute([$ip, $username, now_utc()]);
            return 'Wrong username or password.';
        }

        $db->prepare('DELETE FROM login_attempts WHERE username = ? OR ip = ?')->execute([$username, $ip]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $db->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([now_utc(), $user['id']]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['uid'] = (int) $user['id'];
        self::$current = null;
        return null;
    }

    public static function isLockedOut(PDO $db, string $username, string $ip): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::LOCKOUT_MINUTES * 60);
        $stmt = $db->prepare('SELECT
            SUM(CASE WHEN username = ? THEN 1 ELSE 0 END) AS by_user,
            SUM(CASE WHEN ip = ? THEN 1 ELSE 0 END) AS by_ip
            FROM login_attempts WHERE attempted_at >= ?');
        $stmt->execute([$username, $ip, $since]);
        $row = $stmt->fetch() ?: [];
        return (int) ($row['by_user'] ?? 0) >= self::MAX_FAILURES
            || (int) ($row['by_ip'] ?? 0) >= self::MAX_FAILURES * 4;
    }

    /** The logged-in user, re-read from the database so disabling an account takes effect immediately. */
    public static function user(PDO $db): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) {
            return null;
        }
        $stmt = $db->prepare('SELECT id, username, is_admin, is_active FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user || (int) $user['is_active'] !== 1) {
            unset($_SESSION['uid']);
            return null;
        }
        return self::$current = $user;
    }

    public static function requireLogin(): array
    {
        $user = self::user(App::db());
        if ($user !== null) {
            return $user;
        }
        if (self::wantsJson()) {
            json_response(['error' => 'Not logged in'], 401);
        }
        redirect('login.php');
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ((int) $user['is_admin'] !== 1) {
            http_response_code(403);
            exit('Admins only.');
        }
        return $user;
    }

    public static function logout(): void
    {
        self::$current = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    private static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
