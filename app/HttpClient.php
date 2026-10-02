<?php
declare(strict_types=1);

namespace App;

/** The few HTTP calls providers need. Swapped for a fake in tests. */
interface HttpClient
{
    /** Sends JSON (when $body is given) and returns ['status' => int, 'data' => ?array, 'raw' => string]. */
    public function json(string $method, string $url, array $headers = [], ?array $body = null): array;

    /** Multipart upload of one file; same return shape as json(). */
    public function upload(string $url, array $headers, string $field, string $path, string $mime, string $filename): array;

    /** Streams $url to $dest, failing if it exceeds $maxBytes. */
    public function download(string $url, string $dest, int $maxBytes): void;
}
