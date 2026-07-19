<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

enum RetryClass: string
{
    case Retriable = 'retriable';
    case Fatal = 'fatal';
}

class RetryClassifier
{
    public function classify(Throwable $e): RetryClass
    {
        if ($e instanceof ConnectionException) {
            return RetryClass::Retriable;
        }

        if ($e instanceof RequestException) {
            $status = $e->response?->status();

            if (in_array($status, [401, 404], true)) {
                return RetryClass::Fatal;
            }

            if ($status === 429 || ($status !== null && $status >= 500)) {
                return RetryClass::Retriable;
            }

            if ($status !== null && $status >= 400) {
                return RetryClass::Fatal;
            }
        }

        $message = strtolower($e->getMessage());

        if (str_contains($message, 'validation') || str_contains($message, 'invalid payload') || str_contains($message, 'empty dataset')) {
            return RetryClass::Fatal;
        }

        return RetryClass::Retriable;
    }
}
