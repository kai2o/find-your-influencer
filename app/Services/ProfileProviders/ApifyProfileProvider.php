<?php

namespace App\Services\ProfileProviders;

use App\DataTransferObjects\ProfileData;
use App\Services\Ops\OpsEventRecorder;
use App\Services\TokenBucketLimiter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ApifyProfileProvider implements ProfileProvider
{
    public function __construct(
        private readonly string $token,
        private readonly string $actorId = 'apify~instagram-profile-scraper',
    ) {}

    public function fetch(string $username): ProfileData
    {
        $ops = app(OpsEventRecorder::class);
        $event = $ops->start('api', 'apify.fetch', meta: [
            'username' => $username,
            'actor_id' => $this->actorId,
        ]);

        $runUrl = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items',
            $this->actorId
        );

        $caBundle = ini_get('curl.cainfo') ?: (storage_path('certs/cacert.pem'));
        if (! is_string($caBundle) || $caBundle === '' || ! is_file($caBundle)) {
            $fallback = getenv('LOCALAPPDATA').DIRECTORY_SEPARATOR.'fyi-cacert'.DIRECTORY_SEPARATOR.'cacert.pem';
            $caBundle = is_file($fallback) ? $fallback : $caBundle;
        }

        $request = Http::timeout(60)
            ->connectTimeout(3)
            ->acceptJson()
            ->withToken($this->token);

        if (is_string($caBundle) && is_file($caBundle)) {
            $request = $request->withOptions([
                'verify' => $caBundle,
            ]);
        }

        $httpStatus = null;
        $started = microtime(true);

        try {
            $response = $request->post($runUrl, [
                'usernames' => [$username],
            ]);

            $httpStatus = $response->status();

            if ($response->status() === 401) {
                throw new RequestException($response);
            }

            if ($response->status() === 404) {
                throw new RequestException($response);
            }

            if ($response->serverError() || $response->status() === 429) {
                throw new RequestException($response);
            }

            if ($response->failed()) {
                throw new RequestException($response);
            }

            $items = $response->json();

            if (! is_array($items) || $items === []) {
                throw new RuntimeException('Apify returned an empty dataset for username: '.$username);
            }

            $item = $items[0];

            if (! is_array($item)) {
                throw new RuntimeException('Invalid payload from Apify for username: '.$username);
            }

            if (($item['error'] ?? null) === 'not_found' || ($item['exists'] ?? true) === false) {
                throw new RequestException(
                    Http::response(['message' => 'Profile not found'], 404)
                );
            }

            $pic = $item['profilePicUrlHD'] ?? $item['profilePicUrl'] ?? null;
            $picUrl = is_string($pic) && $pic !== '' ? $pic : null;

            $data = new ProfileData(
                username: (string) ($item['username'] ?? $username),
                bio: isset($item['biography']) ? (string) $item['biography'] : null,
                profilePictureUrl: $picUrl,
                followersCount: (int) ($item['followersCount'] ?? 0),
                followingCount: (int) ($item['followsCount'] ?? 0),
                postsCount: (int) ($item['postsCount'] ?? 0),
            );

            $ops->finish($event, 'success', [
                'username' => $username,
                'http_status' => $httpStatus,
                'outcome' => 'success',
                'api_duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ], tokensConsumed: 1, tokensRemaining: app(TokenBucketLimiter::class)->snapshot()['tokens_remaining']);

            return $data;
        } catch (Throwable $e) {
            $ops->finish($event, 'failed', [
                'username' => $username,
                'http_status' => $httpStatus,
                'outcome' => 'failed',
                'error' => $e->getMessage(),
                'api_duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ], tokensConsumed: 1, tokensRemaining: app(TokenBucketLimiter::class)->snapshot()['tokens_remaining']);

            throw $e;
        }
    }
}
