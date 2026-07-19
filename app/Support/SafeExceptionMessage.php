<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

class SafeExceptionMessage
{
    public static function from(Throwable $e): string
    {
        if ($e instanceof ConnectionException) {
            $raw = $e->getMessage();

            if (str_contains(strtolower($raw), 'ssl') || str_contains($raw, 'certificate')) {
                return 'SSL certificate verification failed when calling the profile API. Check PHP CA certificates (curl.cainfo / openssl.cafile).';
            }

            if (str_contains(strtolower($raw), 'timed out') || str_contains(strtolower($raw), 'timeout')) {
                return 'Timed out connecting to the profile API.';
            }

            return 'Network error while calling the profile API.';
        }

        if ($e instanceof RequestException) {
            $status = $e->response?->status();

            return match ($status) {
                401 => 'Profile API rejected the credentials (401). Check APIFY_TOKEN.',
                404 => 'Profile not found on the provider (404).',
                429 => 'Profile API rate limit reached (429). Will retry later.',
                default => $status
                    ? "Profile API request failed with HTTP {$status}."
                    : 'Profile API request failed.',
            };
        }

        $raw = $e->getMessage();

        if (str_contains($raw, 'value too long for type character varying')
            || str_contains($raw, 'String data, right truncated')) {
            return 'Fetched profile data was too long for a database column. Widen profile_picture_url (or bio) to text and re-fetch.';
        }

        $message = self::redact($raw);

        if ($message === '') {
            return 'Profile fetch failed.';
        }

        return mb_substr($message, 0, 300);
    }

    public static function redact(string $message): string
    {
        $message = preg_replace('/apify_api_[A-Za-z0-9]+/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/([?&]token=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
        $message = preg_replace('#https?://[^\s]+#i', '[url redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;

        return trim($message);
    }
}
