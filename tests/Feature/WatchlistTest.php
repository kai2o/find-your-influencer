<?php

use App\Enums\ProfileStatus;
use App\Models\Profile;
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
