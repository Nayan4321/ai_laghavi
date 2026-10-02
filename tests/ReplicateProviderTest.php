<?php
declare(strict_types=1);

use App\Providers\ReplicateProvider;

$cfg = fn (array $extra = []) => array_replace_recursive([
    'api_token' => 'r8_test',
    'model' => 'owner/video-model',
    'input_map' => ['prompt' => 'prompt', 'aspect_ratio' => 'aspect_ratio', 'image' => 'first_frame', 'width' => null, 'height' => null],
    'aspect_ratio_choices' => ['16:9', '9:16'],
], $extra);
$job = ['id' => 7, 'prompt' => 'a cat surfing', 'width' => 1366, 'height' => 768, 'aspect_ratio' => '683:384', 'duration' => 5];
$image = ['kind' => 'image', 'abs_path' => '/tmp/x.png', 'mime' => 'image/png', 'original_name' => 'x.png'];

return [
    'maps job fields to the configured model inputs' => function () use ($cfg, $job) {
        $p = new ReplicateProvider($cfg(['input_map' => ['width' => 'width', 'height' => 'height', 'duration' => 'seconds'],
            'extra_input' => ['resolution' => '720p']]), new FakeHttp());
        $input = $p->buildInput($job, []);
        ksort($input);
        assert_same([
            'aspect_ratio' => '16:9',
            'height' => 768,
            'prompt' => 'a cat surfing',
            'resolution' => '720p',
            'seconds' => 5,
            'width' => 1366,
        ], $input);
    },
    'exact ratio mode and size strings' => function () use ($cfg, $job) {
        $p = new ReplicateProvider($cfg(['aspect_ratio_mode' => 'exact', 'size_format' => '{width}*{height}',
            'input_map' => ['size' => 'size']]), new FakeHttp());
        $input = $p->buildInput($job, []);
        assert_same('683:384', $input['aspect_ratio']);
        assert_same('1366*768', $input['size']);
    },
    'uploads reference images and passes their URLs' => function () use ($cfg, $job, $image) {
        $http = new FakeHttp();
        $http->queue('UPLOAD https://api.replicate.com/v1/files', 201, ['urls' => ['get' => 'https://api.replicate.com/v1/files/abc']]);
        $http->queue('UPLOAD https://api.replicate.com/v1/files', 201, ['urls' => ['get' => 'https://api.replicate.com/v1/files/def']]);
        $p = new ReplicateProvider($cfg(['input_map' => ['images' => 'refs']]), $http);
        $input = $p->buildInput($job, [$image, $image + ['original_name' => 'y.png']]);
        assert_same('https://api.replicate.com/v1/files/abc', $input['first_frame']);
        assert_same(['https://api.replicate.com/v1/files/abc', 'https://api.replicate.com/v1/files/def'], $input['refs']);
    },
    'skips uploading references the model has no input for' => function () use ($cfg, $job, $image) {
        $http = new FakeHttp();
        $p = new ReplicateProvider($cfg(['input_map' => ['image' => null]]), $http);
        $p->buildInput($job, [$image, ['kind' => 'video'] + $image]);
        assert_same([], $http->requests);
    },
    'submit posts to the model endpoint with the token' => function () use ($cfg, $job) {
        $http = new FakeHttp();
        $http->queue('POST https://api.replicate.com/v1/models/owner/video-model/predictions', 201, ['id' => 'pred123', 'status' => 'starting']);
        $p = new ReplicateProvider($cfg(), $http);
        assert_same('pred123', $p->submit($job, []));
        assert_same(['Authorization: Bearer r8_test'], $http->requests[0]['headers']);
        assert_same('a cat surfing', $http->requests[0]['body']['input']['prompt']);
    },
    'submit uses the versioned endpoint when a version is pinned' => function () use ($cfg, $job) {
        $http = new FakeHttp();
        $http->queue('POST https://api.replicate.com/v1/predictions', 201, ['id' => 'p2']);
        (new ReplicateProvider($cfg(['version' => 'abc123']), $http))->submit($job, []);
        assert_same('abc123', $http->requests[0]['body']['version']);
    },
    'submit surfaces API errors' => function () use ($cfg, $job) {
        $http = new FakeHttp();
        $http->queue('POST https://api.replicate.com/v1/models/owner/video-model/predictions', 422, ['detail' => 'input.prompt is required']);
        assert_throws(RuntimeException::class, fn () => (new ReplicateProvider($cfg(), $http))->submit($job, []), 'input.prompt is required');
    },
    'check maps prediction states' => function () use ($cfg) {
        $http = new FakeHttp();
        $url = 'GET https://api.replicate.com/v1/predictions/p1';
        $http->queue($url, 200, ['status' => 'processing']);
        $http->queue($url, 200, ['status' => 'succeeded', 'output' => 'https://replicate.delivery/x/out.mp4']);
        $http->queue($url, 200, ['status' => 'succeeded', 'output' => ['https://replicate.delivery/x/a.mp4']]);
        $http->queue($url, 200, ['status' => 'failed', 'error' => 'NSFW content detected']);
        $http->queue($url, 200, ['status' => 'succeeded', 'output' => null]);
        $p = new ReplicateProvider($cfg(), $http);
        assert_same('running', $p->check('p1')['state']);
        assert_same('https://replicate.delivery/x/out.mp4', $p->check('p1')['video_url']);
        assert_same('https://replicate.delivery/x/a.mp4', $p->check('p1')['video_url']);
        assert_same(['state' => 'failed', 'video_url' => null, 'error' => 'NSFW content detected'], $p->check('p1'));
        assert_same('failed', $p->check('p1')['state']);
    },
    'requires a token and a model' => function () {
        assert_throws(RuntimeException::class, fn () => new ReplicateProvider(['model' => 'a/b'], new FakeHttp()), 'api_token');
        assert_throws(RuntimeException::class, fn () => new ReplicateProvider(['api_token' => 'x'], new FakeHttp()), 'model');
    },
];
