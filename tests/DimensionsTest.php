<?php
declare(strict_types=1);

use App\Dimensions;

return [
    'preset ratio derives height from width' => function () {
        assert_same([1280, 720, '16:9'], Dimensions::resolve('1280', '999', '16:9', 128, 4096));
        assert_same([1080, 1920, '9:16'], Dimensions::resolve(1080, 0, '9:16', 128, 4096));
    },
    'custom keeps both sides and reduces the ratio' => function () {
        assert_same([1366, 768, '683:384'], Dimensions::resolve('1366', '768', 'custom', 128, 4096));
        assert_same([1000, 1000, '1:1'], Dimensions::resolve('1000', '1000', 'custom', 128, 4096));
    },
    'odd sizes are rounded up to even' => function () {
        assert_same([1002, 502, '501:251'], Dimensions::resolve('1001', '501', 'custom', 128, 4096));
    },
    'rejects out of range and non-numeric sizes' => function () {
        assert_throws(InvalidArgumentException::class, fn () => Dimensions::resolve('64', '64', 'custom', 128, 4096), 'between');
        assert_throws(InvalidArgumentException::class, fn () => Dimensions::resolve('5000', '100', 'custom', 128, 8000), 'Height');
        assert_throws(InvalidArgumentException::class, fn () => Dimensions::resolve('12.5', '100', 'custom', 1, 4096), 'whole number');
        assert_throws(InvalidArgumentException::class, fn () => Dimensions::resolve('abc', '100', 'custom', 1, 4096));
        assert_throws(InvalidArgumentException::class, fn () => Dimensions::resolve('100', '100', '16:0', 1, 4096), 'Aspect');
    },
    'nearest picks the closest supported ratio' => function () {
        assert_same('16:9', Dimensions::nearest(1366, 768, ['16:9', '9:16', '1:1']));
        assert_same('9:16', Dimensions::nearest(700, 1300, ['16:9', '9:16', '1:1']));
        assert_same('1:1', Dimensions::nearest(1000, 1100, ['16:9', '9:16', '1:1']));
        assert_same('21:9', Dimensions::nearest(2560, 1080));
    },
];
