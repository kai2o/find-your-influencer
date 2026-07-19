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

        $data = new ProfileData(
            username: $username,
            bio: 'Fake bio for '.$username,
            profilePictureUrl: 'https://example.com/'.$username.'.jpg',
            followersCount: 1000 + strlen($username),
            followingCount: 100,
            postsCount: 50,
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
