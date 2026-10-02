<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

final class CurlHttpClient implements HttpClient
{
    public function json(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, $opts);
        return $this->finish($ch, $url);
    }

    public function upload(string $url, array $headers, string $field, string $path, string $mime, string $filename): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_POSTFIELDS => [$field => new \CURLFile($path, $mime, $filename)],
        ]);
        return $this->finish($ch, $url);
    }

    public function download(string $url, string $dest, int $maxBytes): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create folder $dir");
        }
        $tmp = $dest . '.part';
        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            throw new RuntimeException("Cannot write $tmp");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static fn ($c, $dlTotal, $dlNow) => ($dlTotal > $maxBytes || $dlNow > $maxBytes) ? 1 : 0,
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($ok === false || $status < 200 || $status >= 300) {
            @unlink($tmp);
            throw new RuntimeException("Download failed (HTTP $status) $err");
        }
        rename($tmp, $dest);
    }

    private function finish(\CurlHandle $ch, string $url): array
    {
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Request to ' . parse_url($url, PHP_URL_HOST) . " failed: $err");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true);
        return ['status' => $status, 'data' => is_array($data) ? $data : null, 'raw' => (string) $raw];
    }
}
