<?php
declare(strict_types=1);

use App\App;
use App\Jobs;
use App\Users;

return [
    'creates regular users with hashed passwords' => function () {
        $db = App::db();
        $id = Users::create($db, 'bob', 'a long password');
        $row = $db->query("SELECT * FROM users WHERE id = $id")->fetch();
        assert_same(0, (int) $row['is_admin']);
        assert_same(1, (int) $row['is_active']);
        assert_true(password_verify('a long password', $row['password_hash']));
    },
    'rejects duplicate usernames, bad usernames and short passwords' => function () {
        $db = App::db();
        Users::create($db, 'bob', 'a long password');
        assert_throws(InvalidArgumentException::class, fn () => Users::create($db, 'bob', 'another password'), 'taken');
        assert_throws(InvalidArgumentException::class, fn () => Users::create($db, 'b o b', 'another password'), 'Usernames');
        assert_throws(InvalidArgumentException::class, fn () => Users::create($db, 'carol', 'short'), 'at least');
    },
    'the admin account cannot be disabled or deleted' => function () {
        $db = App::db();
        $admin = Users::create($db, 'nayan', 'admin password!', true);
        assert_throws(InvalidArgumentException::class, fn () => Users::setActive($db, $admin, false), 'admin');
        assert_throws(InvalidArgumentException::class, fn () => Users::delete($db, $admin), 'admin');
    },
    'deleting a user removes their jobs and returns their files' => function () {
        $db = App::db();
        $id = Users::create($db, 'bob', 'a long password');
        $job = Jobs::create($db, $id, ['prompt' => 'x', 'width' => 640, 'height' => 360, 'aspect_ratio' => '16:9', 'duration' => 5],
            [['kind' => 'image', 'path' => 'uploads/a.png', 'mime' => 'image/png', 'original_name' => 'a.png', 'size_bytes' => 1]], 'mock');
        Jobs::update($db, $job, ['output_path' => 'videos/out.mp4']);
        $files = Users::delete($db, $id);
        sort($files);
        assert_same(['uploads/a.png', 'videos/out.mp4'], $files);
        assert_same(0, (int) $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
        assert_same(0, (int) $db->query('SELECT COUNT(*) FROM job_assets')->fetchColumn());
    },
    'users only see their own jobs, the admin sees all' => function () {
        $db = App::db();
        $a = Users::create($db, 'alice', 'a long password');
        $b = Users::create($db, 'bob', 'a long password');
        $admin = Users::create($db, 'nayan', 'admin password!', true);
        $job = Jobs::create($db, $a, ['prompt' => 'x', 'width' => 640, 'height' => 360, 'aspect_ratio' => '16:9', 'duration' => 5], [], 'mock');
        assert_true(Jobs::findVisible($db, $job, ['id' => $a, 'is_admin' => 0]) !== null, 'owner should see job');
        assert_same(null, Jobs::findVisible($db, $job, ['id' => $b, 'is_admin' => 0]));
        assert_true(Jobs::findVisible($db, $job, ['id' => $admin, 'is_admin' => 1]) !== null, 'admin should see job');
    },
];
