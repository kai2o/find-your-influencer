<?php

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\ApiDailyQuota;
use App\Services\CircuitBreaker;
use App\Services\Ops\OpsEventRecorder;
use App\Services\ProfileFetchLocker;
use App\Services\ProfileProviders\FakeProfileProvider;
use App\Services\ProfileSnapshotWriter;
use App\Services\RetryClassifier;
use App\Services\TokenBucketLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

it('makes only one http call when a lock is already held', function () {
    config(['services.profile.driver' => 'apify']);
    config(['services.apify.token' => 'test-token']);

    $profile = Profile::factory()->create([
        'username' => 'locktest',
        'status' => ProfileStatus::Pending,
    ]);

    Redis::del('api:token-bucket');
    Redis::del('circuit:apify:failures');
    Redis::del('circuit:apify:open_until');
    Redis::del('circuit:apify:half_open');
    Redis::del('quota:'.now('Asia/Kolkata')->toDateString());
    Redis::set('profile-fetch-lock:'.$profile->id, 'held-by-other', 'EX', 120);

    Http::fake([
        'api.apify.com/*' => Http::response([
            [
                'username' => 'locktest',
                'biography' => 'bio',
                'profilePicUrl' => 'https://example.com/a.jpg',
                'followersCount' => 10,
                'followsCount' => 1,
                'postsCount' => 2,
            ],
        ], 200),
    ]);

    $job = new FetchProfileJob($profile->id);
    $provider = new \App\Services\ProfileProviders\ApifyProfileProvider('test-token');
    $job->handle(
        $provider,
        app(ProfileFetchLocker::class),
        app(TokenBucketLimiter::class),
        app(ApiDailyQuota::class),
        app(CircuitBreaker::class),
        app(RetryClassifier::class),
        app(ProfileSnapshotWriter::class),
        app(OpsEventRecorder::class),
    );

    Http::assertNothingSent();

    // Release lock and run again — exactly one HTTP call.
    Redis::del('profile-fetch-lock:'.$profile->id);

    $job->handle(
        $provider,
        app(ProfileFetchLocker::class),
        app(TokenBucketLimiter::class),
        app(ApiDailyQuota::class),
        app(CircuitBreaker::class),
        app(RetryClassifier::class),
        app(ProfileSnapshotWriter::class),
        app(OpsEventRecorder::class),
    );

    Http::assertSentCount(1);
    expect($profile->fresh()->status)->toBe(ProfileStatus::Fetched);
});

it('fetches successfully with fake provider under lock', function () {
    $profile = Profile::factory()->create([
        'username' => 'fakeuser',
        'status' => ProfileStatus::Pending,
        'followers_count' => null,
    ]);

    Redis::del('api:token-bucket');
    Redis::del('circuit:apify:failures');
    Redis::del('circuit:apify:open_until');
    Redis::del('circuit:apify:half_open');
    Redis::del('quota:'.now('Asia/Kolkata')->toDateString());
    Redis::del('profile-fetch-lock:'.$profile->id);

    $job = new FetchProfileJob($profile->id);
    $job->handle(
        new FakeProfileProvider,
        app(ProfileFetchLocker::class),
        app(TokenBucketLimiter::class),
        app(ApiDailyQuota::class),
        app(CircuitBreaker::class),
        app(RetryClassifier::class),
        app(ProfileSnapshotWriter::class),
        app(OpsEventRecorder::class),
    );

    expect($profile->fresh()->status)->toBe(ProfileStatus::Fetched)
        ->and($profile->fresh()->snapshots()->count())->toBe(1);
});
