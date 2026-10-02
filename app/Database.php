<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(array $cfg): PDO
    {
        $dsn = (string) ($cfg['dsn'] ?? '');
        if ($dsn === '') {
            throw new \RuntimeException('Database DSN is not configured in config.php');
        }
        $pdo = new PDO($dsn, $cfg['user'] ?? null, $cfg['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("SET time_zone = '+00:00'");
        }
        return $pdo;
    }
}
