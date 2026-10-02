<?php
declare(strict_types=1);

use App\ConfigWriter;

return [
    'fills config.example.php and keeps it loadable' => function () {
        $root = dirname(__DIR__);
        $out = ConfigWriter::render(file_get_contents($root . '/config.example.php'), [
            'app_name' => 'My Videos',
            'dsn' => ConfigWriter::mysqlDsn('localhost', 'u1_video'),
            'user' => 'u1_user',
            'pass' => "it's a \\ tricky pass",
            'install_token' => 'tok',
            'api_token' => 'r8_abc',
        ]);
        $file = __DIR__ . '/tmp/config-render.php';
        file_put_contents($file, $out);
        $cfg = require $file;
        assert_same('My Videos', $cfg['app_name']);
        assert_same('mysql:host=localhost;dbname=u1_video;charset=utf8mb4', $cfg['db']['dsn']);
        assert_same('u1_user', $cfg['db']['user']);
        assert_same("it's a \\ tricky pass", $cfg['db']['pass']);
        assert_same('r8_abc', $cfg['replicate']['api_token']);
        assert_same('replicate', $cfg['provider']);
        assert_same(__DIR__ . '/tmp/storage', $cfg['storage_path']);
        assert_true(str_contains($out, 'hPanel'), 'comments kept');
    },
    'refuses keys the template lacks' => function () {
        assert_throws(RuntimeException::class, fn () => ConfigWriter::render("<?php return ['a' => ''];", ['b' => 'x']), "'b'");
    },
];
