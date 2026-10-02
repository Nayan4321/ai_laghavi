<?php
declare(strict_types=1);

// Dependency-free test runner:  php tests/run.php [filter]
// Uses SQLite, so no MySQL server is needed to run the tests.

if (PHP_SAPI !== 'cli') {
    exit;
}

$tmp = __DIR__ . '/tmp';
if (!is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}
putenv('VP_CONFIG=' . __DIR__ . '/test-config.php');
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/support.php';

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/*Test.php');
$passed = 0;
$failed = [];
foreach ($files as $file) {
    $tests = require $file;
    foreach ($tests as $name => $fn) {
        $label = basename($file, '.php') . ' › ' . $name;
        if ($filter !== '' && stripos($label, $filter) === false) {
            continue;
        }
        fresh_app();
        try {
            $fn();
            $passed++;
            echo "  ✓ $label\n";
        } catch (Throwable $e) {
            $failed[] = $label;
            echo "  ✗ $label\n      " . get_class($e) . ': ' . $e->getMessage() . "\n      at " .
                basename($e->getFile()) . ':' . $e->getLine() . "\n";
        }
    }
}
echo "\n$passed passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
