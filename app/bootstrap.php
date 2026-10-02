<?php
declare(strict_types=1);

// Shared bootstrap for every web page, cron job and CLI script.

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/helpers.php';

$configFile = getenv('VP_CONFIG') ?: APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit("Missing config.php. Copy config.example.php to config.php and fill it in.\n");
}
App\App::init(require $configFile);

if (PHP_SAPI !== 'cli') {
    App\App::startWebRequest();
}
