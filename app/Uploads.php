<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/** Validates and stores reference images/videos attached to a generation request. */
final class Uploads
{
    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    public const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

    /** Turns PHP's $_FILES['x'] (single or multiple) into a flat list, skipping empty slots. */
    public static function normalize(?array $files): array
    {
        if (!$files || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            $files = array_map(static fn ($v) => [$v], $files);
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) $name,
                'tmp_name' => (string) $files['tmp_name'][$i],
                'size' => (int) $files['size'][$i],
                'error' => (int) $files['error'][$i],
            ];
        }
        return $out;
    }

    /**
     * Checks the file and moves it into <storage>/uploads. Returns the asset row (without job_id);
     * 'path' is relative to the storage folder so the folder can be moved later.
     * $move is injectable so tests can store files that didn't arrive via HTTP upload.
     */
    public static function store(array $file, string $kind, string $storageRoot, int $maxBytes, ?callable $move = null): array
    {
        $label = $kind === 'video' ? 'Reference video' : 'Reference image';
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException("$label '{$file['name']}' failed to upload" .
                ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE ? ' (too large for the server limit).' : '.'));
        }
        if ($file['size'] > $maxBytes) {
            throw new InvalidArgumentException("$label '{$file['name']}' is larger than " . round($maxBytes / 1048576) . ' MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $types = $kind === 'video' ? self::VIDEO_TYPES : self::IMAGE_TYPES;
        if (!isset($types[$mime])) {
            throw new InvalidArgumentException("$label '{$file['name']}' is not a supported type (" .
                implode(', ', array_values($types)) . ').');
        }
        $dir = rtrim($storageRoot, '/') . '/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create upload folder $dir");
        }
        $relative = 'uploads/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
        $path = rtrim($storageRoot, '/') . '/' . $relative;
        $move ??= 'move_uploaded_file';
        if (!$move($file['tmp_name'], $path)) {
            throw new \RuntimeException("Could not save $label '{$file['name']}'.");
        }
        return [
            'kind' => $kind,
            'path' => $relative,
            'mime' => $mime,
            'original_name' => mb_substr(basename($file['name']), 0, 255),
            'size_bytes' => (int) filesize($path),
        ];
    }
}
