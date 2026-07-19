<?php

use App\Services\RetryClass;
use App\Services\RetryClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Psr7\Response as Psr7Response;

it('classifies connection timeouts as retriable', function () {
    $classifier = new RetryClassifier;
    expect($classifier->classify(new ConnectionException('timeout')))->toBe(RetryClass::Retriable);
});

it('classifies 5xx and 429 as retriable', function () {
    $classifier = new RetryClassifier;

    expect($classifier->classify(new RequestException(new Response(new Psr7Response(500)))))->toBe(RetryClass::Retriable);
    expect($classifier->classify(new RequestException(new Response(new Psr7Response(429)))))->toBe(RetryClass::Retriable);
});

it('classifies 401 and 404 as fatal', function () {
    $classifier = new RetryClassifier;

    expect($classifier->classify(new RequestException(new Response(new Psr7Response(401)))))->toBe(RetryClass::Fatal);
    expect($classifier->classify(new RequestException(new Response(new Psr7Response(404)))))->toBe(RetryClass::Fatal);
});

it('classifies validation/bad payload as fatal', function () {
    $classifier = new RetryClassifier;

    expect($classifier->classify(new RuntimeException('Invalid payload from Apify')))->toBe(RetryClass::Fatal);
});
