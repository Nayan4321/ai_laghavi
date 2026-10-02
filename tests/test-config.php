<?php
return [
    'app_name' => 'Test Video',
    'db' => ['dsn' => 'sqlite::memory:'],
    'storage_path' => __DIR__ . '/tmp/storage',
    'install_token' => 'test-install-token-123',
    'provider' => 'mock',
    'mock_sample_video_url' => 'https://example.test/sample.mp4',
];
