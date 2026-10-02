<?php
declare(strict_types=1);

use App\App;
use App\Auth;
use App\HttpClient;
use App\Schema;

final class AssertionFailed extends RuntimeException
{
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_true(mixed $value, string $msg = 'expected true'): void
{
    if ($value !== true) {
        throw new AssertionFailed($msg);
    }
}

function assert_throws(string $class, callable $fn, string $contains = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!$e instanceof $class) {
            throw new AssertionFailed("expected $class, got " . get_class($e) . ': ' . $e->getMessage());
        }
        if ($contains !== '' && stripos($e->getMessage(), $contains) === false) {
            throw new AssertionFailed("exception message '{$e->getMessage()}' does not contain '$contains'");
        }
        return;
    }
    throw new AssertionFailed("expected $class to be thrown");
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

/** New in-memory database and empty storage folder for each test. */
function fresh_app(array $overrides = []): PDO
{
    $config = array_replace_recursive(require __DIR__ . '/test-config.php', $overrides);
    App::init($config);
    rrmdir($config['storage_path']);
    mkdir($config['storage_path'] . '/uploads', 0777, true);
    mkdir($config['storage_path'] . '/videos', 0777, true);
    $db = App::db();
    Schema::create($db);
    $_SESSION = [];
    Auth::reset();
    return $db;
}

/** Records requests and replays canned responses, in order, per "METHOD url" key. */
final class FakeHttp implements HttpClient
{
    public array $requests = [];
    public array $responses = [];
    public array $downloads = [];

    public function queue(string $key, int $status, array $data): void
    {
        $this->responses[$key][] = ['status' => $status, 'data' => $data, 'raw' => json_encode($data)];
    }

    public function json(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return $this->next("$method $url");
    }

    public function upload(string $url, array $headers, string $field, string $path, string $mime, string $filename): array
    {
        $this->requests[] = ['method' => 'UPLOAD', 'url' => $url, 'path' => $path, 'mime' => $mime, 'filename' => $filename];
        return $this->next("UPLOAD $url");
    }

    public function download(string $url, string $dest, int $maxBytes): void
    {
        $this->downloads[] = $url;
        if (str_contains($url, 'broken')) {
            throw new RuntimeException('Download failed (HTTP 500)');
        }
        file_put_contents($dest, 'fake video bytes');
    }

    private function next(string $key): array
    {
        if (empty($this->responses[$key])) {
            throw new RuntimeException("No fake response queued for $key");
        }
        return array_shift($this->responses[$key]);
    }
}

/** A 1×1 PNG written to a temp file, standing in for an uploaded reference image. */
function temp_png(): string
{
    $path = tempnam(sys_get_temp_dir(), 'vp') ;
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    return $path;
}
