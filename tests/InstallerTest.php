<?php
declare(strict_types=1);

use App\App;
use App\Installer;

return [
    'install creates the admin once and then refuses' => function () {
        $db = App::db();
        assert_same(false, Installer::isInstalled($db));
        Installer::install($db, 'nayan', 'admin password!');
        assert_same(1, (int) $db->query("SELECT is_admin FROM users WHERE username = 'nayan'")->fetchColumn());
        assert_true(is_file(Installer::lockFile()), 'lock file written');
        assert_throws(RuntimeException::class, fn () => Installer::install($db, 'mallory', 'another password'), 'Already installed');
    },
    'an existing admin counts as installed even without the lock file' => function () {
        $db = App::db();
        \App\Users::create($db, 'nayan', 'admin password!', true);
        assert_same(true, Installer::isInstalled($db));
    },
];
