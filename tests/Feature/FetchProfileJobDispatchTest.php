<?php

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('runs FetchProfileJob synchronously when provider is fake', function () {
    config(['services.profile.driver' => 'fake']);
    config(['services.apify.token' => null]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/watchlist', ['username' => '@NatGeo'])
        ->assertRedirect();

    $profile = Profile::query()->where('username', 'natgeo')->first();

    expect($profile)->not->toBeNull()
        ->and($profile->status)->toBe(ProfileStatus::Fetched)
        ->and($profile->followers_count)->not->toBeNull();
});

it('queues FetchProfileJob when provider is apify', function () {
    config(['services.profile.driver' => 'apify']);
    config(['services.apify.token' => 'test-token-for-queue-assert']);

    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/watchlist', ['username' => '@NatGeo'])
        ->assertRedirect();

    $profile = Profile::query()->where('username', 'natgeo')->first();

    expect($profile)->not->toBeNull()
        ->and($profile->status)->toBe(ProfileStatus::Pending);

    Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $profile->id);
});

it('runs FetchProfileJob synchronously on refetch when provider is fake', function () {
    config(['services.profile.driver' => 'fake']);
    config(['services.apify.token' => null]);

    $user = User::factory()->create();
    $profile = Profile::factory()->create([
        'status' => ProfileStatus::Fetched,
        'followers_count' => 1000,
    ]);

    $this->actingAs($user)
        ->post(route('watchlist.refetch', $profile))
        ->assertRedirect();

    $profile->refresh();

    expect($profile->status)->toBe(ProfileStatus::Fetched);
});

it('queues FetchProfileJob on refetch when provider is apify', function () {
    config(['services.profile.driver' => 'apify']);
    config(['services.apify.token' => 'test-token-for-queue-assert']);

    Queue::fake();

    $user = User::factory()->create();
    $profile = Profile::factory()->create(['status' => ProfileStatus::Fetched]);

    $this->actingAs($user)
        ->post(route('watchlist.refetch', $profile))
        ->assertRedirect();

    Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $profile->id);
});
