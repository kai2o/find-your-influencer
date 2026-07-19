<?php

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('dispatches FetchProfileJob when adding a handle', function () {
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

it('dispatches FetchProfileJob on refetch', function () {
    Queue::fake();

    $user = User::factory()->create();
    $profile = Profile::factory()->create(['status' => ProfileStatus::Fetched]);

    $this->actingAs($user)
        ->post(route('watchlist.refetch', $profile))
        ->assertRedirect();

    Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $profile->id);
});
