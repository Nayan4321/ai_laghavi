<?php
declare(strict_types=1);

// First-run setup in the browser: checks the MySQL details, creates the tables and the
// admin account, and writes config.php. It only works while config.php doesn't exist,
// and only with working database credentials for this hosting account, so nobody else
// can use it. Once config.php exists this page is gone.
const VP_SETUP = true;
require __DIR__ . '/app/bootstrap.php';

use App\App;
use App\ConfigWriter;
use App\Csrf;
use App\Database;
use App\Installer;
use App\Users;

if (is_file(CONFIG_FILE)) {
    http_response_code(404);
    exit('Not found.');
}

$error = null;
$manualConfig = null;
$in = [
    'app_name' => trim((string) ($_POST['app_name'] ?? 'Laghavi Video')),
    'db_name' => trim((string) ($_POST['db_name'] ?? '')),
    'db_user' => trim((string) ($_POST['db_user'] ?? '')),
    'api_token' => trim((string) ($_POST['api_token'] ?? '')),
    'username' => trim((string) ($_POST['username'] ?? '')),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            throw new InvalidArgumentException('Your session expired. Please submit the form again.');
        }
        foreach (['db_name' => 'database name', 'db_user' => 'database username', 'username' => 'admin username'] as $k => $label) {
            if ($in[$k] === '') {
                throw new InvalidArgumentException("Fill in the $label.");
            }
        }
        if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
            throw new InvalidArgumentException('The admin passwords don’t match.');
        }
        Users::validPassword((string) ($_POST['password'] ?? ''));

        // Hostinger's MySQL for the site is always on localhost; accepting other hosts would let
        // a stranger finish setup against a database they control.
        $dsn = ConfigWriter::mysqlDsn('localhost', $in['db_name']);
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        try {
            $db = Database::connect(['dsn' => $dsn, 'user' => $in['db_user'], 'pass' => $dbPass]);
        } catch (PDOException $e) {
            throw new InvalidArgumentException('Couldn’t connect to the database. Check the name, username and password in hPanel → Databases → MySQL Databases. (' . $e->getMessage() . ')');
        }

        $values = [
            'app_name' => $in['app_name'] !== '' ? $in['app_name'] : 'Laghavi Video',
            'dsn' => $dsn,
            'user' => $in['db_user'],
            'pass' => $dbPass,
            'install_token' => bin2hex(random_bytes(16)),
            'api_token' => $in['api_token'],
        ];
        $config = ConfigWriter::render((string) file_get_contents(APP_ROOT . '/config.example.php'), $values);

        App::init(require_config_string($config));
        App::setDb($db);
        if (!is_writable(App::storagePath()) && !@mkdir(App::storagePath(), 0750, true)) {
            throw new RuntimeException('The storage folder isn’t writable. In File Manager, set its permissions to 755 and try again.');
        }
        if (!Installer::isInstalled($db)) {
            Installer::install($db, $in['username'], (string) $_POST['password']);
        }

        if (@file_put_contents(CONFIG_FILE, $config) === false) {
            $manualConfig = $config;
        } else {
            @chmod(CONFIG_FILE, 0640);
            flash('Setup complete. Log in with your admin account.', 'success');
            redirect('login.php');
        }
    } catch (InvalidArgumentException|RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('setup.php: ' . $e);
        $error = 'Setup failed unexpectedly: ' . $e->getMessage();
    }
}

/** Evaluates the generated config text the same way require would, without writing it first. */
function require_config_string(string $config): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'vpcfg');
    file_put_contents($tmp, str_replace('__DIR__', var_export(APP_ROOT, true), $config));
    try {
        return require $tmp;
    } finally {
        unlink($tmp);
    }
}

render('setup', ['error' => $error, 'in' => $in, 'manualConfig' => $manualConfig, 'title' => 'Setup']);
