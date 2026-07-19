<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Concurrency guard for FetchProfileJob.
 *
 * Production (pgsql): Postgres session advisory lock keyed by profile id.
 * Must release on every exit path (including exceptions) via finally.
 *
 * Local/tests (non-pgsql): Redis lock with TTL = 120s, longer than the
 * expected Apify cold-run (~8–15s) and connect+read timeouts (3s+15s),
 * so a healthy job finishes before TTL. If a job exceeds TTL, Redis
 * releases the key and a second worker may proceed — documented trade-off
 * for non-Postgres environments; use Postgres in production.
 */
class ProfileFetchLocker
{
    private const REDIS_TTL_SECONDS = 120;

    public function attempt(int $profileId, callable $callback): bool
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $this->withAdvisoryLock($profileId, $callback);
        }

        return $this->withRedisLock($profileId, $callback);
    }

    private function withAdvisoryLock(int $profileId, callable $callback): bool
    {
        $acquired = (bool) DB::selectOne(
            'SELECT pg_try_advisory_lock(?) AS acquired',
            [$profileId]
        )->acquired;

        if (! $acquired) {
            return false;
        }

        try {
            $callback();
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [$profileId]);
        }

        return true;
    }

    private function withRedisLock(int $profileId, callable $callback): bool
    {
        $key = 'profile-fetch-lock:'.$profileId;
        $token = bin2hex(random_bytes(16));
        $acquired = (bool) Redis::set($key, $token, 'EX', self::REDIS_TTL_SECONDS, 'NX');

        if (! $acquired) {
            return false;
        }

        try {
            $callback();
        } finally {
            if (Redis::get($key) === $token) {
                Redis::del($key);
            }
        }

        return true;
    }
}
