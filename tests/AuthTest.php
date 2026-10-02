<?php
declare(strict_types=1);

use App\App;
use App\Auth;
use App\Users;

return [
    'correct password logs in' => function () {
        $db = App::db();
        $id = Users::create($db, 'alice', 'correct horse battery');
        assert_same(null, Auth::attempt($db, 'alice', 'correct horse battery', '1.1.1.1'));
        assert_same($id, $_SESSION['uid']);
        assert_same('alice', Auth::user($db)['username']);
    },
    'wrong password or unknown user is rejected with the same message' => function () {
        $db = App::db();
        Users::create($db, 'alice', 'correct horse battery');
        assert_same('Wrong username or password.', Auth::attempt($db, 'alice', 'nope nope nope', '1.1.1.1'));
        assert_same('Wrong username or password.', Auth::attempt($db, 'bob', 'whatever123', '1.1.1.1'));
        assert_same(null, Auth::user($db));
    },
    'disabled users cannot log in' => function () {
        $db = App::db();
        $id = Users::create($db, 'alice', 'correct horse battery');
        Users::setActive($db, $id, false);
        assert_same('Wrong username or password.', Auth::attempt($db, 'alice', 'correct horse battery', '1.1.1.1'));
    },
    'disabling a user ends their existing session' => function () {
        $db = App::db();
        $id = Users::create($db, 'alice', 'correct horse battery');
        Auth::attempt($db, 'alice', 'correct horse battery', '1.1.1.1');
        Users::setActive($db, $id, false);
        Auth::reset();
        assert_same(null, Auth::user($db));
        assert_true(!isset($_SESSION['uid']), 'session uid should be cleared');
    },
    'locks out after repeated failures' => function () {
        $db = App::db();
        Users::create($db, 'alice', 'correct horse battery');
        for ($i = 0; $i < Auth::MAX_FAILURES; $i++) {
            Auth::attempt($db, 'alice', 'wrong password', '2.2.2.2');
        }
        $msg = Auth::attempt($db, 'alice', 'correct horse battery', '3.3.3.3');
        assert_true(str_contains((string) $msg, 'Too many'), "expected lockout, got: $msg");
    },
    'successful login clears earlier failures' => function () {
        $db = App::db();
        Users::create($db, 'alice', 'correct horse battery');
        for ($i = 0; $i < Auth::MAX_FAILURES - 1; $i++) {
            Auth::attempt($db, 'alice', 'wrong password', '2.2.2.2');
        }
        assert_same(null, Auth::attempt($db, 'alice', 'correct horse battery', '2.2.2.2'));
        assert_same(0, (int) $db->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
    },
];
