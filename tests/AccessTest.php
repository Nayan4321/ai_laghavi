<?php
declare(strict_types=1);

// Checks that every page sits behind login, both by reading the source and by
// requesting pages from PHP's built-in web server.

$root = dirname(__DIR__);
$public = ['login.php', 'install.php', 'setup.php', 'cron.php'];

/** Starts `php -S` against a fresh SQLite file database and returns [baseUrl, stop]. */
function start_server(string $root, bool $withConfig = true): array
{
    $dir = __DIR__ . '/tmp/server';
    rrmdir($dir);
    mkdir($dir . '/storage', 0777, true);
    $config = $dir . '/config.php';
    // Built from config.example.php, the same way setup.php writes it.
    file_put_contents($config, App\ConfigWriter::render(file_get_contents($root . '/config.example.php'), [
        'app_name' => 'Server Test',
        'dsn' => 'sqlite:' . $dir . '/db.sqlite',
        'install_token' => 'server-install-token',
    ]));

    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    if (!$withConfig) {
        unlink($config); // VP_CONFIG then points at a missing file, like a fresh upload.
    }
    $env = ['VP_CONFIG' => $config, 'PATH' => getenv('PATH')];
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root], [1 => ['file', $dir . '/server.log', 'a'], 2 => ['file', $dir . '/server.log', 'a']], $pipes, $root, $env);
    $base = "http://127.0.0.1:$port";
    for ($i = 0; $i < 50; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            break;
        }
        usleep(100000);
    }
    return [$base, function () use ($proc) {
        proc_terminate($proc);
        proc_close($proc);
    }];
}

/** Minimal browser: keeps cookies, doesn't follow redirects. */
final class Browser
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->send($path, null, $headers);
    }

    public function post(string $path, array $fields): array
    {
        return $this->send($path, $fields);
    }

    public function csrf(string $path): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->get($path)['body'], $m);
        return $m[1] ?? '';
    }

    private function send(string $path, ?array $fields, array $headers = []): array
    {
        $ch = curl_init($this->base . '/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);
        if ($fields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $headerSize);
        preg_match('/^Location: (.*)$/mi', $head, $m);
        return ['status' => $status, 'location' => trim($m[1] ?? ''), 'body' => substr($raw, $headerSize)];
    }
}

return [
    'every page except login and install requires login' => function () use ($root, $public) {
        foreach (glob($root . '/*.php') as $file) {
            $name = basename($file);
            if (in_array($name, $public, true) || str_starts_with($name, 'config')) {
                continue;
            }
            $src = file_get_contents($file);
            assert_true(
                (bool) preg_match('/Auth::require(Login|Admin)\(\)/', $src),
                "$name must call Auth::requireLogin() or Auth::requireAdmin()",
            );
        }
    },
    'admin page requires the admin role' => function () use ($root) {
        assert_true(str_contains(file_get_contents($root . '/admin.php'), 'Auth::requireAdmin()'), 'admin.php uses requireAdmin');
    },
    'cron and CLI scripts refuse web requests' => function () use ($root) {
        foreach ([...glob($root . '/cron/*.php'), ...glob($root . '/cli/*.php')] as $file) {
            assert_true(str_contains(file_get_contents($file), "PHP_SAPI !== 'cli'"), basename($file) . ' must be CLI-only');
        }
    },
    'live server without config.php: everything leads to setup, which needs real database details' => function () use ($root) {
        [$base, $stop] = start_server($root, false);
        try {
            $guest = new Browser($base);
            foreach (['index.php', 'login.php', 'dashboard.php', 'admin.php'] as $page) {
                $r = $guest->get($page);
                assert_same(302, $r['status'], "$page status");
                assert_same('setup.php', $r['location'], "$page redirect");
            }
            $page = $guest->get('setup.php');
            assert_same(200, $page['status']);
            assert_true(str_contains($page['body'], 'Database name'), 'setup form shown');
            $r = $guest->post('setup.php', ['_csrf' => $guest->csrf('setup.php'), 'db_name' => 'nope', 'db_user' => 'nope',
                'db_pass' => 'nope', 'username' => 'mallory', 'password' => 'mallory password', 'confirm' => 'mallory password']);
            assert_same(200, $r['status']);
            assert_true(str_contains($r['body'], 'connect to the database'), 'bad database details rejected');
            assert_true(!is_file(__DIR__ . '/tmp/server/config.php'), 'no config written');
        } finally {
            $stop();
        }
    },
    'setup.php is gone once config.php exists' => function () use ($root) {
        [$base, $stop] = start_server($root);
        try {
            assert_same(404, (new Browser($base))->get('setup.php')['status']);
        } finally {
            $stop();
        }
    },
    'cron.php checks its secret key before doing anything' => function () use ($root) {
        $src = file_get_contents($root . '/cron.php');
        assert_true(strpos($src, 'hash_equals(WorkerRunner::cronKey()') < strpos($src, 'WorkerRunner::run('), 'key checked first');
    },
    'there is no signup page' => function () use ($root) {
        foreach (glob($root . '/*.php') as $file) {
            assert_true(!preg_match('/sign.?up|register/i', basename($file)), basename($file) . ' looks like a signup page');
        }
    },
    'live server: install, login, roles and generation' => function () use ($root) {
        [$base, $stop] = start_server($root);
        try {
            $guest = new Browser($base);
            foreach (['index.php', 'dashboard.php', 'admin.php', 'account.php', 'video.php?id=1'] as $page) {
                $r = $guest->get($page);
                assert_same(302, $r['status'], "$page status");
                assert_same('login.php', $r['location'], "$page redirect");
            }
            assert_same(401, $guest->get('job_status.php', ['Accept: application/json'])['status'], 'json 401');
            $r = $guest->post('generate.php', ['prompt' => 'x']);
            assert_same('login.php', $r['location'], 'generate needs login');

            $login = $guest->get('login.php');
            assert_same(200, $login['status']);
            assert_true(!preg_match('/sign ?up|register|create account/i', $login['body']), 'login page offers no signup');

            // Install: wrong token rejected, right token creates the admin, then install.php disappears.
            $r = $guest->post('install.php', ['_csrf' => $guest->csrf('install.php'), 'token' => 'nope',
                'username' => 'nayan', 'password' => 'admin password!', 'confirm' => 'admin password!']);
            assert_true(str_contains($r['body'], 'Wrong install token'), 'bad token rejected');
            $r = $guest->post('install.php', ['_csrf' => $guest->csrf('install.php'), 'token' => 'server-install-token',
                'username' => 'nayan', 'password' => 'admin password!', 'confirm' => 'admin password!']);
            assert_same('login.php', $r['location'], 'install redirects to login');
            assert_same(404, $guest->get('install.php')['status'], 'install closed afterwards');

            $admin = new Browser($base);
            $r = $admin->post('login.php', ['_csrf' => $admin->csrf('login.php'), 'username' => 'nayan', 'password' => 'admin password!']);
            assert_same('dashboard.php', $r['location'], 'admin login');
            assert_same(200, $admin->get('admin.php')['status']);

            // Without a CSRF token, admin actions are refused.
            assert_same(400, $admin->post('admin.php', ['action' => 'create', 'username' => 'eve', 'password' => 'eve password!'])['status']);
            $r = $admin->post('admin.php', ['_csrf' => $admin->csrf('admin.php'), 'action' => 'create', 'username' => 'priya', 'password' => 'priya password']);
            assert_same('admin.php', $r['location']);
            assert_true(str_contains($admin->get('admin.php')['body'], 'priya'), 'new user listed');

            $user = new Browser($base);
            $r = $user->post('login.php', ['_csrf' => $user->csrf('login.php'), 'username' => 'priya', 'password' => 'priya password']);
            assert_same('dashboard.php', $r['location'], 'user login');
            assert_same(403, $user->get('admin.php')['status'], 'regular users cannot open the admin panel');

            $png = new CURLFile(temp_png(), 'image/png', 'ref.png');
            $r = $user->post('generate.php', ['_csrf' => $user->csrf('dashboard.php'), 'prompt' => 'a red kite over the sea',
                'aspect' => 'custom', 'width' => '1366', 'height' => '768', 'duration' => '5', 'images[]' => $png]);
            assert_same('dashboard.php', $r['location']);
            $dash = $user->get('dashboard.php')['body'];
            assert_true(str_contains($dash, '1366×768') && str_contains($dash, '683:384'), 'job listed with exact size');
            $status = json_decode($user->get('job_status.php', ['Accept: application/json'])['body'], true);
            assert_same('queued', $status['jobs'][0]['status']);

            // No API token yet: polling runs a fallback worker pass, which reports the problem and leaves the job queued.
            $status = json_decode($user->get('job_status.php', ['Accept: application/json'])['body'], true);
            assert_same('queued', $status['jobs'][0]['status']);
            assert_true(str_contains((string) $status['setup_error'], 'api_token'), 'setup error reported');
            assert_true(str_contains($user->get('dashboard.php')['body'], 'Videos can’t start yet'), 'dashboard explains the wait');

            // Settings: admin only; saving the token writes config.php.
            assert_same(403, $user->get('settings.php')['status']);
            assert_same(403, $user->post('update.php', ['_csrf' => $user->csrf('dashboard.php')])['status'], 'only the admin can update the site');
            $page = $admin->get('settings.php')['body'];
            assert_true(str_contains($page, '/cron/worker.php'), 'cron command shown');
            assert_true(str_contains($page, 'Upload and update'), 'update form shown');

            // Cron by URL: needs the secret key from Settings, and records a cron run.
            preg_match('#/cron\.php\?key=([a-f0-9]+)#', $page, $m);
            assert_true(isset($m[1]), 'cron URL shown');
            assert_same(404, $guest->get('cron.php')['status']);
            assert_same(404, $guest->get('cron.php?key=wrong')['status']);
            $r = $guest->get('cron.php?key=' . $m[1]);
            assert_same(200, $r['status']);
            assert_true(str_starts_with($r['body'], 'ok'), 'cron ran: ' . $r['body']);
            assert_true(str_contains($admin->get('settings.php')['body'], 'The cron job is running'), 'settings shows cron running');
            $r = $admin->post('settings.php', ['_csrf' => $admin->csrf('settings.php'), 'api_token' => 'r8_live', 'model' => 'owner/model']);
            assert_same('settings.php', $r['location']);
            $cfg = require __DIR__ . '/tmp/server/config.php';
            assert_same('r8_live', $cfg['replicate']['api_token']);
            assert_same('owner/model', $cfg['replicate']['model']);

            // Someone else's video is not reachable.
            assert_same(404, $admin->get('video.php?id=999')['status']);
        } finally {
            $stop();
        }
    },
];
