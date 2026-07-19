<?php

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Models\ProfileSnapshot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('returns watchlist inertia props with profiles', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create([
        'username' => 'cristiano',
        'status' => ProfileStatus::Fetched,
    ]);

    $this->actingAs($user)
        ->get('/watchlist')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('watchlist/index')
            ->has('profiles.data', 1)
            ->where('profiles.data.0.username', 'cristiano')
            ->where('profiles.data.0.status', 'fetched')
        );
});

it('deletes a profile and cascades snapshots', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create([
        'username' => 'to_remove',
        'status' => ProfileStatus::Fetched,
    ]);
    ProfileSnapshot::factory()->create(['profile_id' => $profile->id]);

    $this->actingAs($user)
        ->delete(route('watchlist.destroy', $profile))
        ->assertRedirect(route('watchlist.index'));

    expect(Profile::query()->whereKey($profile->id)->exists())->toBeFalse()
        ->and(ProfileSnapshot::query()->where('profile_id', $profile->id)->exists())->toBeFalse();
});

it('refuses to delete a profile that is currently fetching', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create([
        'username' => 'busy_handle',
        'status' => ProfileStatus::Fetching,
    ]);

    $this->actingAs($user)
        ->from(route('watchlist.show', $profile))
        ->delete(route('watchlist.destroy', $profile))
        ->assertRedirect(route('watchlist.show', $profile));

    expect(Profile::query()->whereKey($profile->id)->exists())->toBeTrue();
});
