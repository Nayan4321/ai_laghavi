<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Holds config and lazily created services for the current request. */
final class App
{
    private static array $config = [];
    private static ?PDO $db = null;

    public static function init(array $config): void
    {
        self::$config = $config;
        self::$db = null;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function db(): PDO
    {
        if (self::$db === null) {
            self::$db = Database::connect(self::config('db', []));
        }
        return self::$db;
    }

    public static function setDb(PDO $db): void
    {
        self::$db = $db;
    }

    public static function storagePath(string $sub = ''): string
    {
        $base = rtrim((string) self::config('storage_path', APP_ROOT . '/storage'), '/');
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }

    public static function provider(?HttpClient $http = null): Providers\VideoProvider
    {
        $name = (string) self::config('provider', 'replicate');
        $http ??= new CurlHttpClient();
        return match ($name) {
            'replicate' => new Providers\ReplicateProvider(self::config('replicate', []), $http),
            'mock' => new Providers\MockProvider((string) self::config('mock_sample_video_url', '')),
            default => throw new \RuntimeException("Unknown video provider '$name' in config.php"),
        };
    }

    public static function startWebRequest(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('vpsess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; media-src 'self' blob:; frame-ancestors 'none'; form-action 'self'");
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
