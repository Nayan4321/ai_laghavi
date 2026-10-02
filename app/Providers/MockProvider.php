<?php
declare(strict_types=1);

namespace App\Providers;

/** Pretends to generate: every job succeeds on its first check with a fixed sample video URL. For trying the site without an API key. */
final class MockProvider implements VideoProvider
{
    public function __construct(private string $sampleUrl = '')
    {
    }

    public function name(): string
    {
        return 'mock';
    }

    public function submit(array $job, array $assets): string
    {
        return 'mock-' . $job['id'];
    }

    public function check(string $providerJobId): array
    {
        if ($this->sampleUrl === '') {
            return ['state' => 'failed', 'video_url' => null, 'error' => 'Mock provider: set mock_sample_video_url in config.php to a public .mp4 URL.'];
        }
        return ['state' => 'succeeded', 'video_url' => $this->sampleUrl, 'error' => null];
    }
}
