<?php
declare(strict_types=1);

// SSH alternative to install.php:  php cli/install.php <admin-username>
// Prompts for the password so it doesn't end up in shell history.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$username = $argv[1] ?? '';
if ($username === '') {
    fwrite(STDERR, "Usage: php cli/install.php <admin-username>\n");
    exit(1);
}
echo 'Admin password (10+ characters): ';
system('stty -echo 2>/dev/null');
$password = trim((string) fgets(STDIN));
system('stty echo 2>/dev/null');
echo "\n";

try {
    App\Installer::install(App\App::db(), $username, $password);
    echo "Installed. Log in as $username, then delete install.php from the server.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
