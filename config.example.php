<?php
// Copy this file to config.php and fill it in. config.php is never committed.
return [
    'app_name' => 'Laghavi Video',

    // hPanel → Databases → MySQL Databases. Hostinger prefixes names with your account id, e.g. u123456789_video.
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=u123456789_video;charset=utf8mb4',
        'user' => 'u123456789_video',
        'pass' => 'change-me',
    ],

    // Where uploads and finished videos are kept. Best outside public_html, e.g. /home/u123456789/video-storage.
    // If you keep the default (inside the site), the bundled .htaccess blocks direct web access to it.
    'storage_path' => __DIR__ . '/storage',

    // Needed once by install.php to create the admin account. Use a long random value.
    'install_token' => '',

    'dimensions' => ['min' => 128, 'max' => 4096],
    'duration' => ['min' => 1, 'max' => 20],
    'max_reference_images' => 4,
    'upload_max_mb' => 50,              // Also raise upload_max_filesize/post_max_size in hPanel → PHP Configuration.
    'max_active_jobs_per_user' => 3,    // Caps spend: queued + processing videos per user.
    'job_timeout_minutes' => 120,
    'max_video_mb' => 500,

    // 'replicate' for real generation, or 'mock' to try the site without an API key.
    'provider' => 'replicate',
    'mock_sample_video_url' => '',

    'replicate' => [
        // replicate.com → Account → API tokens.
        'api_token' => '',

        // The model to run, as "owner/name" from its replicate.com page. Or set 'version' to pin an exact version id.
        // The default takes a prompt, an optional starting image and 16:9 or 9:16. Pick any other video model the same way.
        'model' => 'google/veo-3-fast',
        'version' => '',

        // Which of the model's inputs receives each value. Check the model's "API" tab on replicate.com and
        // set unsupported ones to null. 'image' gets the first reference image, 'images' gets all of them.
        'input_map' => [
            'prompt' => 'prompt',
            'width' => null,
            'height' => null,
            'size' => null,              // For models that take one size string, formatted by size_format.
            'aspect_ratio' => 'aspect_ratio',
            'duration' => null,
            'image' => 'image',
            'images' => null,
            'video' => null,
        ],
        'size_format' => '{width}x{height}',

        // Models with a fixed list of ratios get the closest one ('nearest'); 'exact' sends the reduced ratio, e.g. 683:384.
        'aspect_ratio_mode' => 'nearest',
        'aspect_ratio_choices' => ['16:9', '9:16'],

        // Fixed extra inputs sent with every request, e.g. ['resolution' => '720p'].
        'extra_input' => [],
    ],
];
