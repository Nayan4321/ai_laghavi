<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Jobs
{
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    public static function create(PDO $db, int $userId, array $data, array $assets, string $provider): int
    {
        $db->beginTransaction();
        try {
            $now = now_utc();
            $db->prepare('INSERT INTO jobs (user_id, prompt, width, height, aspect_ratio, duration, status, provider, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $data['prompt'], $data['width'], $data['height'], $data['aspect_ratio'],
                    $data['duration'], self::QUEUED, $provider, $now, $now]);
            $jobId = (int) $db->lastInsertId();
            $ins = $db->prepare('INSERT INTO job_assets (job_id, kind, path, mime, original_name, size_bytes) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($assets as $a) {
                $ins->execute([$jobId, $a['kind'], $a['path'], $a['mime'], $a['original_name'], $a['size_bytes']]);
            }
            $db->commit();
            return $jobId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function find(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** A job the given user may see: their own, or any job for the admin. */
    public static function findVisible(PDO $db, int $id, array $user): ?array
    {
        $job = self::find($db, $id);
        if (!$job || ((int) $job['user_id'] !== (int) $user['id'] && (int) $user['is_admin'] !== 1)) {
            return null;
        }
        return $job;
    }

    public static function forUser(PDO $db, int $userId, int $limit = 50): array
    {
        $stmt = $db->prepare('SELECT * FROM jobs WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function activeCount(PDO $db, int $userId): int
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM jobs WHERE user_id = ? AND status IN (?, ?)');
        $stmt->execute([$userId, self::QUEUED, self::PROCESSING]);
        return (int) $stmt->fetchColumn();
    }

    public static function assets(PDO $db, int $jobId): array
    {
        $stmt = $db->prepare('SELECT * FROM job_assets WHERE job_id = ? ORDER BY id');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    }

    public static function byStatus(PDO $db, string $status, int $limit): array
    {
        $stmt = $db->prepare('SELECT * FROM jobs WHERE status = ? ORDER BY id LIMIT ' . (int) $limit);
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    public static function update(PDO $db, int $id, array $fields): void
    {
        $fields['updated_at'] = now_utc();
        $sets = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        $db->prepare("UPDATE jobs SET $sets WHERE id = ?")->execute([...array_values($fields), $id]);
    }

    /** Deletes one job and returns the files to remove from disk. */
    public static function delete(PDO $db, int $id): array
    {
        $job = self::find($db, $id);
        if (!$job) {
            return [];
        }
        $files = array_column(self::assets($db, $id), 'path');
        if ($job['output_path']) {
            $files[] = $job['output_path'];
        }
        $db->prepare('DELETE FROM job_assets WHERE job_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM jobs WHERE id = ?')->execute([$id]);
        return $files;
    }

    public static function filesForUser(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT a.path FROM job_assets a JOIN jobs j ON j.id = a.job_id WHERE j.user_id = ?');
        $stmt->execute([$userId]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $db->prepare('SELECT output_path FROM jobs WHERE user_id = ? AND output_path IS NOT NULL');
        $stmt->execute([$userId]);
        return array_merge($files, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Paths are relative to the storage folder. */
    public static function removeFiles(array $paths): void
    {
        foreach ($paths as $p) {
            if ($p && !str_contains($p, '..') && is_file(App::storagePath($p))) {
                @unlink(App::storagePath($p));
            }
        }
    }
}
