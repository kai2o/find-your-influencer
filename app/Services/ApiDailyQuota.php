<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Daily API quota counter keyed by IST calendar date (assignment §4.B.3).
 * Ceiling defaults to 1000 units/day; refuse when within 10% of the ceiling.
 */
class ApiDailyQuota
{
    public function __construct(
        private readonly int $dailyCeiling = 1000,
        private readonly float $refuseAtFraction = 0.9,
    ) {}

    public function keyForToday(): string
    {
        return 'quota:'.now('Asia/Kolkata')->toDateString();
    }

    public function usedToday(): int
    {
        return (int) (Redis::get($this->keyForToday()) ?: 0);
    }

    public function remainingToday(): int
    {
        return max(0, $this->dailyCeiling - $this->usedToday());
    }

    public function ceiling(): int
    {
        return $this->dailyCeiling;
    }

    /**
     * Returns false when within 10% of the daily ceiling (or over).
     */
    public function allows(int $units = 1): bool
    {
        $used = $this->usedToday();
        $threshold = (int) floor($this->dailyCeiling * $this->refuseAtFraction);

        if ($used >= $threshold || ($used + $units) > $this->dailyCeiling) {
            Log::warning(json_encode([
                'event' => 'quota_stop',
                'key' => $this->keyForToday(),
                'used' => $used,
                'ceiling' => $this->dailyCeiling,
                'threshold' => $threshold,
            ], JSON_THROW_ON_ERROR));

            return false;
        }

        return true;
    }

    public function consume(int $units = 1): int
    {
        $key = $this->keyForToday();
        $used = (int) Redis::incrby($key, $units);
        Redis::expire($key, 60 * 60 * 48);

        return $used;
    }
}
