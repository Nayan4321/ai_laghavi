<?php
declare(strict_types=1);

use App\Updater;

function make_zip(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'vpzip') . '.zip';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
    return $path;
}

function make_site(): string
{
    $root = __DIR__ . '/tmp/site';
    rrmdir($root);
    mkdir($root . '/app', 0777, true);
    mkdir($root . '/storage/videos', 0777, true);
    file_put_contents($root . '/index.php', 'old index');
    file_put_contents($root . '/app/bootstrap.php', 'old bootstrap');
    file_put_contents($root . '/config.php', 'my secrets');
    file_put_contents($root . '/storage/videos/v.mp4', 'my video');
    file_put_contents($root . '/extra.php', 'kept');
    return $root;
}

return [
    'installs a GitHub-style ZIP and leaves config and storage alone' => function () {
        $root = make_site();
        $zip = make_zip([
            'ai_laghavi-main/index.php' => 'new index',
            'ai_laghavi-main/app/bootstrap.php' => 'new bootstrap',
            'ai_laghavi-main/app/New.php' => 'brand new',
            'ai_laghavi-main/config.php' => 'attacker config',
            'ai_laghavi-main/storage/videos/v.mp4' => 'overwritten',
            'ai_laghavi-main/.git/config' => 'x',
        ]);
        $result = Updater::apply($zip, $root, $root . '/storage/backups');
        assert_same(3, $result['updated']);
        assert_same('new index', file_get_contents($root . '/index.php'));
        assert_same('brand new', file_get_contents($root . '/app/New.php'));
        assert_same('my secrets', file_get_contents($root . '/config.php'));
        assert_same('my video', file_get_contents($root . '/storage/videos/v.mp4'));
        assert_same('kept', file_get_contents($root . '/extra.php'));
        assert_true(!is_dir($root . '/.git'), '.git not created');

        $backup = new ZipArchive();
        $backup->open($result['backup']);
        assert_same('old index', $backup->getFromName('index.php'));
        assert_same('old bootstrap', $backup->getFromName('app/bootstrap.php'));
        assert_same(false, $backup->getFromName('app/New.php'));
    },
    'a backup ZIP can be uploaded again to undo' => function () {
        $root = make_site();
        $first = Updater::apply(make_zip(['x/index.php' => 'v2', 'x/app/bootstrap.php' => 'b2']), $root, $root . '/storage/backups');
        Updater::apply($first['backup'], $root, $root . '/storage/backups');
        assert_same('old index', file_get_contents($root . '/index.php'));
    },
    'ignores paths that escape the site folder' => function () {
        $root = make_site();
        Updater::apply(make_zip(['index.php' => 'n', 'app/bootstrap.php' => 'b', '../escaped.php' => 'bad', 'a/../../escaped2.php' => 'bad']), $root, $root . '/storage/backups');
        assert_true(!is_file(dirname($root) . '/escaped.php') && !is_file(dirname($root) . '/escaped2.php'), 'nothing written outside');
        assert_same('n', file_get_contents($root . '/index.php'));
    },
    'rejects ZIPs that are not this site, and non-ZIP files' => function () {
        $root = make_site();
        assert_throws(RuntimeException::class, fn () => Updater::apply(make_zip(['readme.txt' => 'hi']), $root, $root . '/storage/backups'), 'doesn’t look like');
        $notZip = tempnam(sys_get_temp_dir(), 'vp');
        file_put_contents($notZip, 'plain text');
        assert_throws(RuntimeException::class, fn () => Updater::apply($notZip, $root, $root . '/storage/backups'), 'readable ZIP');
        assert_same('old index', file_get_contents($root . '/index.php'));
    },
    'keeps only the latest backups' => function () {
        $root = make_site();
        for ($i = 0; $i < 7; $i++) {
            Updater::apply(make_zip(['index.php' => "v$i", 'app/bootstrap.php' => 'b']), $root, $root . '/storage/backups');
        }
        assert_same(5, count(glob($root . '/storage/backups/backup-*.zip')));
    },
];
