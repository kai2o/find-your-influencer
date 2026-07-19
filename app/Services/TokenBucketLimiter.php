<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Token-bucket rate limiter stored in Redis.
 * Defaults: capacity 100, refill 10/min (~1 every 6 seconds). Overridable via env.
 */
class TokenBucketLimiter
{
    public function __construct(
        private readonly string $key = 'api:token-bucket',
        private readonly int $capacity = 100,
        private readonly float $refillPerSecond = 10 / 60,
    ) {}

    /**
     * @return array{allowed: bool, tokens_remaining: float, tokens_consumed: int}
     */
    public function attempt(): array
    {
        if (! config('services.rate_limits.enabled', true)) {
            return [
                'allowed' => true,
                'tokens_remaining' => (float) $this->capacity,
                'tokens_consumed' => 0,
            ];
        }

        $now = microtime(true);
        $raw = Redis::hgetall($this->key);

        $tokens = isset($raw['tokens']) ? (float) $raw['tokens'] : (float) $this->capacity;
        $updatedAt = isset($raw['updated_at']) ? (float) $raw['updated_at'] : $now;

        $elapsed = max(0, $now - $updatedAt);
        $tokens = min($this->capacity, $tokens + ($elapsed * $this->refillPerSecond));

        if ($tokens < 1) {
            Redis::hmset($this->key, [
                'tokens' => $tokens,
                'updated_at' => $now,
            ]);

            return [
                'allowed' => false,
                'tokens_remaining' => round($tokens, 2),
                'tokens_consumed' => 0,
            ];
        }

        $tokens -= 1;

        Redis::hmset($this->key, [
            'tokens' => $tokens,
            'updated_at' => $now,
        ]);

        return [
            'allowed' => true,
            'tokens_remaining' => round($tokens, 2),
            'tokens_consumed' => 1,
        ];
    }

    /**
     * @return array{tokens_remaining: float, capacity: int}
     */
    public function snapshot(): array
    {
        if (! config('services.rate_limits.enabled', true)) {
            return [
                'tokens_remaining' => (float) $this->capacity,
                'capacity' => $this->capacity,
            ];
        }

        $now = microtime(true);
        $raw = Redis::hgetall($this->key);

        $tokens = isset($raw['tokens']) ? (float) $raw['tokens'] : (float) $this->capacity;
        $updatedAt = isset($raw['updated_at']) ? (float) $raw['updated_at'] : $now;
        $elapsed = max(0, $now - $updatedAt);
        $tokens = min($this->capacity, $tokens + ($elapsed * $this->refillPerSecond));

        return [
            'tokens_remaining' => round($tokens, 2),
            'capacity' => $this->capacity,
        ];
    }
}
