<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $title ??= App\App::config('app_name', 'Video Studio');
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/app/views/layout.php';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed');
    }
    App\Csrf::verifyOrFail($_POST['_csrf'] ?? null);
}
