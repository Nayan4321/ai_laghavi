<?php
declare(strict_types=1);

namespace App;

use RuntimeException;
use ZipArchive;

/**
 * Installs a new version of the site from a ZIP of the code (for example GitHub's
 * "Download ZIP"), so updates don't need File Manager. config.php and the storage
 * folder are never touched, and every file about to be replaced is backed up first.
 */
final class Updater
{
    /** Never overwritten by an update. */
    private const PROTECTED = ['config.php', '.user.ini', 'storage/', '.git/'];
    private const KEEP_BACKUPS = 5;

    /** @return array{updated: int, backup: ?string} */
    public static function apply(string $zipPath, string $root, string $backupDir): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('This server’s PHP has no ZIP support. Enable the "zip" extension in hPanel → PHP Configuration.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('That file isn’t a readable ZIP.');
        }
        try {
            $files = self::entries($zip);
            if (!isset($files['app/bootstrap.php'], $files['index.php'])) {
                throw new RuntimeException('That ZIP doesn’t look like this site’s code (no app/bootstrap.php or index.php inside).');
            }

            $root = rtrim($root, '/');
            $backup = self::backup($files, $root, $backupDir);

            foreach ($files as $path => $index) {
                $target = $root . '/' . $path;
                $dir = dirname($target);
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException("Can’t create folder $path. Check folder permissions in File Manager.");
                }
                $contents = $zip->getFromIndex($index);
                if ($contents === false) {
                    throw new RuntimeException("Can’t read $path from the ZIP.");
                }
                // Write next to the target, then rename, so a half-written file is never served.
                $tmp = $target . '.updating';
                if (file_put_contents($tmp, $contents) === false || !rename($tmp, $target)) {
                    @unlink($tmp);
                    throw new RuntimeException("Can’t write $path. Check file permissions in File Manager." .
                        ($backup ? ' Files replaced so far are in ' . basename($backup) . '.' : ''));
                }
            }
        } finally {
            $zip->close();
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return ['updated' => count($files), 'backup' => $backup];
    }

    /**
     * Maps safe, relative file paths to ZIP indexes. Strips the single top folder GitHub
     * adds (e.g. "ai_laghavi-main/"), and drops protected or unsafe paths.
     */
    private static function entries(ZipArchive $zip): array
    {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/')) {
                continue;
            }
            $names[$i] = $name;
        }
        if (!$names) {
            throw new RuntimeException('That ZIP is empty.');
        }

        $first = explode('/', reset($names), 2)[0] . '/';
        $hasPrefix = !in_array('index.php', $names, true);
        foreach ($names as $name) {
            if (!str_starts_with($name, $first)) {
                $hasPrefix = false;
                break;
            }
        }

        $files = [];
        foreach ($names as $i => $name) {
            $path = $hasPrefix ? substr($name, strlen($first)) : $name;
            if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")
                || in_array('..', explode('/', $path), true) || preg_match('/^[A-Za-z]:/', $path)) {
                continue;
            }
            foreach (self::PROTECTED as $p) {
                if ($path === $p || (str_ends_with($p, '/') && str_starts_with($path, $p))) {
                    continue 2;
                }
            }
            $files[$path] = $i;
        }
        return $files;
    }

    /** Zips the current copies of files about to be replaced; returns the backup path, or null if nothing existed. */
    private static function backup(array $files, string $root, string $backupDir): ?string
    {
        $existing = array_filter(array_keys($files), static fn ($p) => is_file($root . '/' . $p));
        if (!$existing) {
            return null;
        }
        if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Can’t create the backups folder in storage.');
        }
        $path = rtrim($backupDir, '/') . '/backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Can’t write a backup to the storage folder.');
        }
        foreach ($existing as $p) {
            $zip->addFile($root . '/' . $p, $p);
        }
        $zip->close();

        $all = glob(rtrim($backupDir, '/') . '/backup-*.zip') ?: [];
        sort($all);
        foreach (array_slice($all, 0, max(0, count($all) - self::KEEP_BACKUPS)) as $old) {
            @unlink($old);
        }
        return $path;
    }
}
