<?php
declare(strict_types=1);

namespace App\Providers;

use App\Dimensions;
use App\HttpClient;
use RuntimeException;

/**
 * Replicate (replicate.com) runs many video models behind one API, so switching
 * model is a config change. Each model names its inputs differently; config's
 * input_map says which input receives the prompt, size, references, etc.
 * Reference files are uploaded to Replicate's files API, so nothing on this site
 * has to be publicly reachable.
 */
final class ReplicateProvider implements VideoProvider
{
    private const API = 'https://api.replicate.com/v1';

    public function __construct(private array $cfg, private HttpClient $http)
    {
        if (empty($cfg['api_token'])) {
            throw new RuntimeException('Replicate api_token is not set in config.php');
        }
        if (empty($cfg['model']) && empty($cfg['version'])) {
            throw new RuntimeException('Set replicate.model (e.g. "owner/model-name") in config.php');
        }
    }

    public function name(): string
    {
        return 'replicate';
    }

    public function submit(array $job, array $assets): string
    {
        $input = $this->buildInput($job, $assets);
        if (!empty($this->cfg['version'])) {
            $url = self::API . '/predictions';
            $body = ['version' => $this->cfg['version'], 'input' => $input];
        } else {
            $url = self::API . '/models/' . $this->cfg['model'] . '/predictions';
            $body = ['input' => $input];
        }
        $res = $this->http->json('POST', $url, $this->headers(), $body);
        if ($res['status'] >= 300 || empty($res['data']['id'])) {
            throw new RuntimeException('Replicate rejected the request: ' . $this->errorText($res));
        }
        return (string) $res['data']['id'];
    }

    public function check(string $providerJobId): array
    {
        $res = $this->http->json('GET', self::API . '/predictions/' . rawurlencode($providerJobId), $this->headers());
        if ($res['status'] >= 300 || !is_array($res['data'])) {
            throw new RuntimeException('Replicate status check failed: ' . $this->errorText($res));
        }
        $d = $res['data'];
        switch ($d['status'] ?? '') {
            case 'succeeded':
                $url = $this->extractUrl($d['output'] ?? null);
                return $url === null
                    ? ['state' => 'failed', 'video_url' => null, 'error' => 'The model finished but returned no video.']
                    : ['state' => 'succeeded', 'video_url' => $url, 'error' => null];
            case 'failed':
            case 'canceled':
                return ['state' => 'failed', 'video_url' => null, 'error' => (string) ($d['error'] ?? 'Generation ' . $d['status'])];
            default:
                return ['state' => 'running', 'video_url' => null, 'error' => null];
        }
    }

    /** Public so tests can check the mapping without HTTP. */
    public function buildInput(array $job, array $assets): array
    {
        $map = ($this->cfg['input_map'] ?? []) + [
            'prompt' => 'prompt', 'width' => null, 'height' => null, 'size' => null,
            'aspect_ratio' => null, 'duration' => null, 'image' => null, 'images' => null, 'video' => null,
        ];
        $w = (int) $job['width'];
        $h = (int) $job['height'];
        $values = [
            'prompt' => (string) $job['prompt'],
            'width' => $w,
            'height' => $h,
            'size' => strtr((string) ($this->cfg['size_format'] ?? '{width}x{height}'), ['{width}' => $w, '{height}' => $h]),
            'aspect_ratio' => ($this->cfg['aspect_ratio_mode'] ?? 'nearest') === 'exact'
                ? (string) $job['aspect_ratio']
                : Dimensions::nearest($w, $h, $this->cfg['aspect_ratio_choices'] ?? Dimensions::PRESETS),
            'duration' => (int) $job['duration'],
        ];

        $images = array_values(array_filter($assets, static fn ($a) => $a['kind'] === 'image'));
        $videos = array_values(array_filter($assets, static fn ($a) => $a['kind'] === 'video'));
        if ($images && ($map['image'] || $map['images'])) {
            $urls = array_map(fn ($a) => $this->uploadFile($a), $images);
            $values['image'] = $urls[0];
            $values['images'] = $urls;
        }
        if ($videos && $map['video']) {
            $values['video'] = $this->uploadFile($videos[0]);
        }

        $input = $this->cfg['extra_input'] ?? [];
        foreach ($map as $key => $field) {
            if ($field && array_key_exists($key, $values)) {
                $input[$field] = $values[$key];
            }
        }
        return $input;
    }

    private function uploadFile(array $asset): string
    {
        $res = $this->http->upload(self::API . '/files', $this->headers(), 'content',
            $asset['abs_path'], $asset['mime'], $asset['original_name']);
        $url = $res['data']['urls']['get'] ?? null;
        if ($res['status'] >= 300 || !$url) {
            throw new RuntimeException("Uploading reference '{$asset['original_name']}' to Replicate failed: " . $this->errorText($res));
        }
        return (string) $url;
    }

    private function extractUrl(mixed $output): ?string
    {
        if (is_string($output) && str_starts_with($output, 'http')) {
            return $output;
        }
        if (is_array($output)) {
            foreach ($output as $item) {
                $url = $this->extractUrl($item);
                if ($url !== null) {
                    return $url;
                }
            }
        }
        return null;
    }

    private function headers(): array
    {
        return ['Authorization: Bearer ' . $this->cfg['api_token']];
    }

    private function errorText(array $res): string
    {
        $detail = $res['data']['detail'] ?? $res['data']['error'] ?? $res['data']['title'] ?? substr($res['raw'] ?? '', 0, 300);
        return 'HTTP ' . $res['status'] . ' ' . (is_string($detail) ? $detail : json_encode($detail));
    }
}
