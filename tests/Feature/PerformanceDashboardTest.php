<?php

use App\Models\User;
use App\Services\Ops\OpsEventRecorder;
use Inertia\Testing\AssertableInertia as Assert;

it('shows recorded ops events on the performance page for authenticated users', function () {
    $user = User::factory()->create();
    $ops = app(OpsEventRecorder::class);

    $event = $ops->start('job', 'FetchProfileJob');
    \App\Models\Profile::query()->count();
    $ops->finish($event, 'success', [
        'outcome' => 'success',
    ], tokensConsumed: 1, tokensRemaining: 99.0);

    $this->actingAs($user)
        ->get('/performance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('performance/index')
            ->has('summary')
            ->where('summary.tokens_utilized', 1)
            ->where('summary.tokens_capacity', 100)
            ->has('events.data', 1)
            ->where('events.data.0.type', 'job')
            ->where('events.data.0.tokens_consumed', 1)
            ->where('events.data.0.tokens_remaining', 99)
        );
});

it('redirects guests away from the performance page', function () {
    $this->get('/performance')->assertRedirect('/login');
});
