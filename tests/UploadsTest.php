<?php
declare(strict_types=1);

use App\App;
use App\Uploads;

$copy = fn ($from, $to) => copy($from, $to);

return [
    'normalize flattens multi-file uploads and skips empty slots' => function () {
        $files = Uploads::normalize([
            'name' => ['a.png', '', 'b.jpg'],
            'tmp_name' => ['/tmp/a', '', '/tmp/b'],
            'size' => [10, 0, 20],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
        ]);
        assert_same(['a.png', 'b.jpg'], array_column($files, 'name'));
        assert_same(1, count(Uploads::normalize(['name' => 'v.mp4', 'tmp_name' => '/tmp/v', 'size' => 5, 'error' => 0])));
        assert_same([], Uploads::normalize(null));
    },
    'stores a real image under storage/uploads' => function () use ($copy) {
        $tmp = temp_png();
        $asset = Uploads::store(['name' => 'ref.png', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => 0], 'image', App::storagePath(), 1048576, $copy);
        assert_same('image/png', $asset['mime']);
        assert_true(str_starts_with($asset['path'], 'uploads/') && str_ends_with($asset['path'], '.png'), 'relative path');
        assert_true(is_file(App::storagePath($asset['path'])), 'file stored');
    },
    'rejects files whose content is not the declared kind' => function () use ($copy) {
        $tmp = tempnam(sys_get_temp_dir(), 'vp');
        file_put_contents($tmp, '<?php echo "hi";');
        assert_throws(InvalidArgumentException::class,
            fn () => Uploads::store(['name' => 'evil.png', 'tmp_name' => $tmp, 'size' => 16, 'error' => 0], 'image', App::storagePath(), 1048576, $copy),
            'not a supported type');
        $png = temp_png();
        assert_throws(InvalidArgumentException::class,
            fn () => Uploads::store(['name' => 'ref.png', 'tmp_name' => $png, 'size' => 70, 'error' => 0], 'video', App::storagePath(), 1048576, $copy),
            'not a supported type');
    },
    'rejects oversized and failed uploads' => function () use ($copy) {
        $tmp = temp_png();
        assert_throws(InvalidArgumentException::class,
            fn () => Uploads::store(['name' => 'big.png', 'tmp_name' => $tmp, 'size' => 2 * 1048576, 'error' => 0], 'image', App::storagePath(), 1048576, $copy),
            'larger than');
        assert_throws(InvalidArgumentException::class,
            fn () => Uploads::store(['name' => 'x.png', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE], 'image', App::storagePath(), 1048576, $copy),
            'server limit');
    },
];
