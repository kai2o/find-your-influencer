<?php

use Illuminate\Support\Facades\Redis;

it('accepts a valid hmac signature', function () {
    $secret = 'test-webhook-secret';
    config(['services.webhook.secret' => $secret]);

    $payload = json_encode(['id' => 'evt-1', 'username' => 'cristiano']);
    $signature = hash_hmac('sha256', $payload, $secret);

    Redis::del('webhook:replay:apify:evt-1');

    $this->call(
        'POST',
        '/webhooks/apify',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_WEBHOOK_ID' => 'evt-1',
        ],
        $payload
    )->assertOk()->assertJson(['status' => 'accepted']);
});

it('rejects an invalid hmac signature', function () {
    config(['services.webhook.secret' => 'test-webhook-secret']);

    $payload = json_encode(['id' => 'evt-2', 'username' => 'cristiano']);

    $this->call(
        'POST',
        '/webhooks/apify',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'bad-signature',
            'HTTP_X_WEBHOOK_ID' => 'evt-2',
        ],
        $payload
    )->assertStatus(401);
});

it('rejects a replayed webhook within 24h', function () {
    $secret = 'test-webhook-secret';
    config(['services.webhook.secret' => $secret]);

    $payload = json_encode(['id' => 'evt-3', 'username' => 'cristiano']);
    $signature = hash_hmac('sha256', $payload, $secret);
    Redis::del('webhook:replay:apify:evt-3');

    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        'HTTP_X_WEBHOOK_ID' => 'evt-3',
    ];

    $this->call('POST', '/webhooks/apify', [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', '/webhooks/apify', [], [], [], $headers, $payload)->assertStatus(409);
});
