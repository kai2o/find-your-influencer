<?php

it('returns degraded when queue has not processed recently', function () {
    Illuminate\Support\Facades\Redis::del('queue:last_processed_at');

    $this->getJson('/healthz')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonFragment(['failing' => ['queue']]);
});

it('returns ok when db redis and recent queue activity are healthy', function () {
    Illuminate\Support\Facades\Redis::set('queue:last_processed_at', now()->timestamp);

    $this->getJson('/healthz')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});
