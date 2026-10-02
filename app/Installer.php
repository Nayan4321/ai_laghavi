<?php
declare(strict_types=1);

namespace App;

use PDO;

/** First-time setup: creates the tables and the single admin account. */
final class Installer
{
    public static function lockFile(): string
    {
        return App::storagePath('installed.lock');
    }

    public static function isInstalled(PDO $db): bool
    {
        if (is_file(self::lockFile())) {
            return true;
        }
        try {
            return Users::hasAdmin($db);
        } catch (\PDOException) {
            return false; // Tables don't exist yet.
        }
    }

    public static function install(PDO $db, string $username, string $password): int
    {
        if (self::isInstalled($db)) {
            throw new \RuntimeException('Already installed. Manage users from the admin panel.');
        }
        Schema::create($db);
        $id = Users::create($db, $username, $password, true);
        foreach (['', 'uploads', 'videos'] as $sub) {
            $dir = App::storagePath($sub);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
        }
        file_put_contents(self::lockFile(), 'Installed ' . now_utc() . "\n");
        return $id;
    }
}
