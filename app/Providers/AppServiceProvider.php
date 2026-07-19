<?php

namespace App\Providers;

use App\Services\ApiDailyQuota;
use App\Services\Ops\OpsEventRecorder;
use App\Services\ProfileProviders\ApifyProfileProvider;
use App\Services\ProfileProviders\FakeProfileProvider;
use App\Services\ProfileProviders\ProfileProvider;
use App\Services\TokenBucketLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpsEventRecorder::class);

        $this->app->singleton(ApiDailyQuota::class, function () {
            return new ApiDailyQuota(
                dailyCeiling: (int) env('API_DAILY_QUOTA', 1000),
            );
        });

        $this->app->singleton(TokenBucketLimiter::class, function () {
            $capacity = max(1, (int) config('services.rate_limits.token_bucket_capacity', 100));
            $refillPerMinute = max(0.0, (float) config('services.rate_limits.token_bucket_refill_per_minute', 10));

            return new TokenBucketLimiter(
                capacity: $capacity,
                refillPerSecond: $refillPerMinute / 60,
            );
        });

        $this->app->bind(ProfileProvider::class, function () {
            $driver = config('services.profile.driver', 'apify');

            if ($driver === 'fake' || empty(config('services.apify.token'))) {
                return new FakeProfileProvider;
            }

            return new ApifyProfileProvider(
                token: (string) config('services.apify.token'),
                actorId: (string) config('services.apify.actor_id', 'apify~instagram-profile-scraper'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
