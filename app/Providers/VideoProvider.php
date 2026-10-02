<?php
declare(strict_types=1);

namespace App\Providers;

/**
 * An external text/image-to-video API. To add another provider, implement this
 * and register it in App::provider().
 */
interface VideoProvider
{
    public function name(): string;

    /**
     * Starts generation and returns the provider's id for the request.
     * $assets are job_assets rows with an extra 'abs_path' key.
     */
    public function submit(array $job, array $assets): string;

    /**
     * Returns ['state' => 'running'|'succeeded'|'failed', 'video_url' => ?string, 'error' => ?string].
     */
    public function check(string $providerJobId): array;
}
