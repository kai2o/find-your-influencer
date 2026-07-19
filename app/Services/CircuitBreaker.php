<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Circuit breaker state machine (Redis counter + open-until timestamp):
 *
 * closed --[10 consecutive failures]--> open
 * open --[2 minutes elapsed]--> half_open
 * half_open --[probe success]--> closed
 * half_open --[probe failure]--> open
 */
class CircuitBreaker
{
    private const FAILURE_KEY = 'circuit:apify:failures';

    private const OPEN_UNTIL_KEY = 'circuit:apify:open_until';

    private const HALF_OPEN_KEY = 'circuit:apify:half_open';

    public function __construct(
        private readonly int $failureThreshold = 10,
        private readonly int $openSeconds = 120,
    ) {}

    public function allowsRequest(): bool
    {
        $openUntil = Redis::get(self::OPEN_UNTIL_KEY);

        if ($openUntil === null) {
            return true;
        }

        if (now()->timestamp < (int) $openUntil) {
            return false;
        }

        // Half-open: allow exactly one probe.
        if (Redis::set(self::HALF_OPEN_KEY, '1', 'EX', $this->openSeconds, 'NX')) {
            return true;
        }

        return false;
    }

    public function recordSuccess(): void
    {
        Redis::del(self::FAILURE_KEY, self::OPEN_UNTIL_KEY, self::HALF_OPEN_KEY);
    }

    public function recordFailure(): void
    {
        $failures = (int) Redis::incr(self::FAILURE_KEY);

        if ($failures >= $this->failureThreshold) {
            Redis::set(self::OPEN_UNTIL_KEY, now()->addSeconds($this->openSeconds)->timestamp);
            Redis::del(self::HALF_OPEN_KEY);
        }
    }

    public function state(): string
    {
        $openUntil = Redis::get(self::OPEN_UNTIL_KEY);

        if ($openUntil === null) {
            return 'closed';
        }

        if (now()->timestamp < (int) $openUntil) {
            return 'open';
        }

        return Redis::exists(self::HALF_OPEN_KEY) ? 'half_open' : 'half_open';
    }
}
