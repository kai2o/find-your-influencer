<?php

namespace App\Services\ProfileProviders;

use App\DataTransferObjects\ProfileData;
use App\Services\Ops\OpsEventRecorder;
use App\Services\TokenBucketLimiter;

class FakeProfileProvider implements ProfileProvider
{
    public function fetch(string $username): ProfileData
    {
        $ops = app(OpsEventRecorder::class);
        $event = $ops->start('api', 'fake.fetch', meta: ['username' => $username]);
        $started = microtime(true);

        // Stable base metrics from username hash; slight time jitter so refetch shows a delta.
        $seed = crc32(strtolower($username));
        $baseFollowers = 10_000 + ($seed % 500_000);
        $jitter = (int) (now()->timestamp % 17) - 8; // -8..+8
        $followers = max(100, $baseFollowers + $jitter);

        $data = new ProfileData(
            username: $username,
            bio: 'Demo profile for @'.$username,
            profilePictureUrl: 'https://i.pravatar.cc/150?u='.urlencode($username),
            followersCount: $followers,
            followingCount: 100 + ($seed % 400),
            postsCount: 50 + ($seed % 200),
        );

        $ops->finish($event, 'success', [
            'username' => $username,
            'outcome' => 'success',
            'http_status' => 200,
            'api_duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ], tokensConsumed: 1, tokensRemaining: app(TokenBucketLimiter::class)->snapshot()['tokens_remaining']);

        return $data;
    }
}
