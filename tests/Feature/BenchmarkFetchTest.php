<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('benchmarks fake provider fetch timings', function () {
    config(['services.profile.driver' => 'fake']);
    config(['services.apify.token' => '']);

    Profile::factory()->create(['username' => 'bench_one']);
    Profile::factory()->create(['username' => 'bench_two']);

    $exit = Artisan::call('profiles:benchmark-fetch', [
        'usernames' => ['bench_one', 'bench_two'],
    ]);

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('@bench_one')->toContain('@bench_two')->toContain('api_ms');
});

it('shows scheduler dispatched count on performance summary', function () {
    $user = User::factory()->create();
    $ops = app(\App\Services\Ops\OpsEventRecorder::class);
    $event = $ops->start('scheduler', 'profiles:refresh-stale');
    $ops->finish($event, 'success', [
        'outcome' => 'success',
        'dispatched_count' => 3,
    ]);

    $this->actingAs($user)
        ->get('/performance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('performance/index')
            ->where('summary.last_scheduler.dispatched_count', 3)
            ->where('events.data.0.meta.dispatched_count', 3)
        );
});
