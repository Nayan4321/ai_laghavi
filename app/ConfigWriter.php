<?php
declare(strict_types=1);

namespace App;

/**
 * Builds config.php from config.example.php, filling in the values entered on
 * setup.php while keeping the example's comments for anyone editing it later.
 */
final class ConfigWriter
{
    /** @param array<string, string> $values key name in the example file => new value */
    public static function render(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $pattern = "/('" . preg_quote($key, '/') . "'\s*=>\s*)'(?:[^'\\\\]|\\\\.)*'/";
            $count = 0;
            $template = preg_replace_callback($pattern, static fn ($m) => $m[1] . var_export($value, true), $template, 1, $count);
            if ($count !== 1) {
                throw new \RuntimeException("config.example.php has no '$key' setting to fill in.");
            }
        }
        return $template;
    }

    public static function mysqlDsn(string $host, string $database): string
    {
        return 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4';
    }
}
