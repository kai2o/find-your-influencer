<?php

namespace App\Providers;

use App\Services\Ops\OpsEventRecorder;
use App\Services\ProfileProviders\ApifyProfileProvider;
use App\Services\ProfileProviders\FakeProfileProvider;
use App\Services\ProfileProviders\ProfileProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpsEventRecorder::class);

        $this->app->singleton(\App\Services\ApiDailyQuota::class, function () {
            return new \App\Services\ApiDailyQuota(
                dailyCeiling: (int) env('API_DAILY_QUOTA', 1000),
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
