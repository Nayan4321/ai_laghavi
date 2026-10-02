<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use PDO;

/** Account management. Only the admin panel and the installer call this. */
final class Users
{
    public const MIN_PASSWORD = 10;

    public static function create(PDO $db, string $username, string $password, bool $isAdmin = false): int
    {
        $username = self::validUsername($username);
        self::validPassword($password);
        $exists = $db->prepare('SELECT 1 FROM users WHERE username = ?');
        $exists->execute([$username]);
        if ($exists->fetchColumn()) {
            throw new InvalidArgumentException("The username '$username' is already taken.");
        }
        $db->prepare('INSERT INTO users (username, password_hash, is_admin, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $isAdmin ? 1 : 0, now_utc()]);
        return (int) $db->lastInsertId();
    }

    public static function setPassword(PDO $db, int $id, string $password): void
    {
        self::validPassword($password);
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function setActive(PDO $db, int $id, bool $active): void
    {
        self::guardAdmin($db, $id);
        $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    }

    /** Deletes the user and their jobs; returns file paths that should be removed from disk. */
    public static function delete(PDO $db, int $id): array
    {
        self::guardAdmin($db, $id);
        $files = Jobs::filesForUser($db, $id);
        $db->prepare('DELETE FROM job_assets WHERE job_id IN (SELECT id FROM jobs WHERE user_id = ?)')->execute([$id]);
        $db->prepare('DELETE FROM jobs WHERE user_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        return $files;
    }

    public static function all(PDO $db): array
    {
        return $db->query('SELECT u.id, u.username, u.is_admin, u.is_active, u.created_at, u.last_login_at,
                (SELECT COUNT(*) FROM jobs j WHERE j.user_id = u.id) AS job_count
            FROM users u ORDER BY u.is_admin DESC, u.username')->fetchAll();
    }

    public static function hasAdmin(PDO $db): bool
    {
        return (bool) $db->query('SELECT 1 FROM users WHERE is_admin = 1 LIMIT 1')->fetchColumn();
    }

    public static function validPassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD) {
            throw new InvalidArgumentException('Passwords must be at least ' . self::MIN_PASSWORD . ' characters.');
        }
        if (strlen($password) > 200) {
            throw new InvalidArgumentException('Passwords must be at most 200 characters.');
        }
    }

    private static function validUsername(string $username): string
    {
        $username = trim($username);
        if (!preg_match('/^[A-Za-z0-9._@-]{3,64}$/', $username)) {
            throw new InvalidArgumentException('Usernames are 3 to 64 characters: letters, numbers, dot, dash, underscore or @.');
        }
        return $username;
    }

    /** The admin account can't be disabled or deleted from the panel, so Nayan can't lock themselves out. */
    private static function guardAdmin(PDO $db, int $id): void
    {
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $isAdmin = $stmt->fetchColumn();
        if ($isAdmin === false) {
            throw new InvalidArgumentException('That user no longer exists.');
        }
        if ((int) $isAdmin === 1) {
            throw new InvalidArgumentException('The admin account cannot be disabled or deleted.');
        }
    }
}
