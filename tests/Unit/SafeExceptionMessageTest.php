<?php

use App\Support\SafeExceptionMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Psr7\Response as Psr7Response;

it('redacts apify tokens and urls from messages', function () {
    $raw = 'cURL error 60 for https://api.apify.com/v2/acts/x?token=apify_api_SECRETTOKEN123';

    expect(SafeExceptionMessage::redact($raw))
        ->not->toContain('apify_api_')
        ->not->toContain('https://')
        ->toContain('[url redacted]');
});

it('maps ssl connection failures to a safe message', function () {
    $e = new ConnectionException('cURL error 60: SSL certificate problem for https://api.apify.com/?token=apify_api_SECRET');

    $safe = SafeExceptionMessage::from($e);

    expect($safe)->toContain('SSL certificate')
        ->and($safe)->not->toContain('apify_api_')
        ->and($safe)->not->toContain('token=');
});

it('maps http status codes without leaking urls', function () {
    $e = new RequestException(new Response(new Psr7Response(401)));

    expect(SafeExceptionMessage::from($e))->toContain('401')
        ->and(SafeExceptionMessage::from($e))->not->toContain('http');
});
