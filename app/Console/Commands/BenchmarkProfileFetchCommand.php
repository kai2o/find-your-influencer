<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\Ops\OpsEventRecorder;
use App\Services\ProfileProviders\ProfileProvider;
use App\Services\TokenBucketLimiter;
use Illuminate\Console\Command;
use Throwable;

class BenchmarkProfileFetchCommand extends Command
{
    protected $signature = 'profiles:benchmark-fetch
                            {usernames?* : Instagram usernames to fetch (defaults to watchlist sample)}
                            {--persist : Also write ops_events rows marked as benchmark}';

    protected $description = 'Benchmark real provider fetch timings (API ms) for given handles';

    public function handle(ProfileProvider $provider, TokenBucketLimiter $bucket, OpsEventRecorder $ops): int
    {
        $usernames = $this->argument('usernames');

        if ($usernames === []) {
            $usernames = Profile::query()
                ->orderBy('username')
                ->limit(10)
                ->pluck('username')
                ->all();
        }

        if ($usernames === []) {
            $usernames = ['cristiano', 'nasa', 'natgeo', 'ka_st_bh'];
        }

        $usernames = array_values(array_unique(array_map(
            fn ($u) => Profile::normalizeUsername((string) $u),
            $usernames
        )));

        $this->info('Benchmarking '.count($usernames).' handle(s) via '. $provider::class);
        $rows = [];

        foreach ($usernames as $username) {
            $started = microtime(true);
            $event = null;
            $status = 'success';
            $httpStatus = null;
            $followers = null;
            $error = null;

            if ($this->option('persist')) {
                $event = $ops->start('api', 'benchmark.fetch', meta: [
                    'username' => $username,
                    'benchmark' => true,
                ]);
            }

            try {
                $data = $provider->fetch($username);
                $followers = $data->followersCount;
                $apiMs = (int) ((microtime(true) - $started) * 1000);
                // Nested provider also records its own ops event; outer timing includes that.
                $httpStatus = 200;

                if ($event) {
                    $ops->finish($event, 'success', [
                        'username' => $username,
                        'outcome' => 'success',
                        'benchmark' => true,
                        'api_duration_ms' => $apiMs,
                        'http_status' => $httpStatus,
                        'followers_count' => $followers,
                    ], tokensConsumed: 0, tokensRemaining: $bucket->snapshot()['tokens_remaining']);
                }

                $rows[] = [
                    'username' => '@'.$username,
                    'status' => $status,
                    'api_ms' => $apiMs,
                    'followers' => number_format($followers),
                    'error' => '',
                ];
                $this->line(sprintf('  %-20s %6d ms  followers=%s', '@'.$username, $apiMs, number_format($followers)));
            } catch (Throwable $e) {
                $status = 'failed';
                $apiMs = (int) ((microtime(true) - $started) * 1000);
                $error = $e->getMessage();

                if ($event) {
                    $ops->finish($event, 'failed', [
                        'username' => $username,
                        'outcome' => 'failed',
                        'benchmark' => true,
                        'api_duration_ms' => $apiMs,
                        'error' => $error,
                    ], tokensConsumed: 0, tokensRemaining: $bucket->snapshot()['tokens_remaining']);
                }

                $rows[] = [
                    'username' => '@'.$username,
                    'status' => $status,
                    'api_ms' => $apiMs,
                    'followers' => '—',
                    'error' => mb_substr($error, 0, 80),
                ];
                $this->error(sprintf('  %-20s %6d ms  FAILED: %s', '@'.$username, $apiMs, mb_substr($error, 0, 120)));
            }
        }

        $this->newLine();
        $this->table(['username', 'status', 'api_ms', 'followers', 'error'], $rows);

        $ok = array_values(array_filter($rows, fn ($r) => $r['status'] === 'success'));
        if ($ok !== []) {
            $times = array_column($ok, 'api_ms');
            $this->info(sprintf(
                'OK: %d/%d · min %d ms · max %d ms · avg %d ms · bucket left %.1f/%d',
                count($ok),
                count($rows),
                min($times),
                max($times),
                (int) round(array_sum($times) / count($times)),
                $bucket->snapshot()['tokens_remaining'],
                $bucket->snapshot()['capacity'],
            ));
        }

        return self::SUCCESS;
    }
}
