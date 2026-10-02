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

define('CONFIG_FILE', getenv('VP_CONFIG') ?: APP_ROOT . '/config.php');
if (is_file(CONFIG_FILE)) {
    App\App::init(require CONFIG_FILE);
} elseif (defined('VP_SETUP')) {
    App\App::init([]); // setup.php runs before config.php exists and writes it.
} elseif (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Missing config.php. Open setup.php in your browser, or copy config.example.php to config.php.\n");
    exit(1);
} else {
    header('Location: setup.php');
    exit;
}

if (PHP_SAPI !== 'cli') {
    App\App::startWebRequest();
}
