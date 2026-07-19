<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\Ops\OpsEventRecorder;
use App\Services\ProfileProviders\ProfileProvider;
use App\Services\TokenBucketLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class BenchmarkProfileFetchCommand extends Command
{
    protected $signature = 'profiles:benchmark-fetch
                            {usernames?* : Instagram usernames to fetch (defaults to watchlist sample)}
                            {--persist : Also write ops_events rows marked as benchmark}
                            {--job : Also run full FetchProfileJob path (lock + quota + bucket + snapshot) and report job_ms}';

    protected $description = 'Benchmark provider API ms and optional full job path timings';

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

        $this->info('Provider: '.$provider::class);
        $this->info('Benchmarking '.count($usernames).' handle(s)'.($this->option('job') ? ' (api + full job)' : ' (api only)'));
        $rows = [];

        foreach ($usernames as $username) {
            $started = microtime(true);
            $event = null;
            $status = 'success';
            $followers = null;
            $error = '';
            $apiMs = null;
            $jobMs = null;

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

                if ($event) {
                    $ops->finish($event, 'success', [
                        'username' => $username,
                        'outcome' => 'success',
                        'benchmark' => true,
                        'api_duration_ms' => $apiMs,
                        'http_status' => 200,
                        'followers_count' => $followers,
                    ], tokensConsumed: 0, tokensRemaining: $bucket->snapshot()['tokens_remaining']);
                }

                if ($this->option('job')) {
                    $this->resetJobGuards();

                    $profile = Profile::query()->firstOrCreate(
                        ['username' => $username],
                        [
                            'platform' => 'instagram',
                            'status' => ProfileStatus::Pending,
                        ]
                    );
                    $profile->update([
                        'status' => ProfileStatus::Pending,
                        'last_error' => null,
                    ]);

                    $jobStarted = microtime(true);
                    FetchProfileJob::dispatchSync($profile->id);
                    $jobMs = (int) ((microtime(true) - $jobStarted) * 1000);
                    $profile->refresh();
                    $followers = $profile->followers_count ?? $followers;
                    $status = $profile->status === ProfileStatus::Fetched ? 'success' : $profile->status->value;
                }

                $rows[] = [
                    'username' => '@'.$username,
                    'status' => $status,
                    'api_ms' => $apiMs,
                    'job_ms' => $jobMs ?? '—',
                    'followers' => $followers !== null ? number_format((int) $followers) : '—',
                    'error' => '',
                ];
                $this->line(sprintf(
                    '  %-20s api=%6d ms%s  followers=%s',
                    '@'.$username,
                    $apiMs,
                    $jobMs !== null ? sprintf('  job=%6d ms', $jobMs) : '',
                    number_format((int) $followers),
                ));
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
                    'job_ms' => $jobMs ?? '—',
                    'followers' => '—',
                    'error' => mb_substr($error, 0, 80),
                ];
                $this->error(sprintf('  %-20s %6d ms  FAILED: %s', '@'.$username, $apiMs, mb_substr($error, 0, 120)));
            }
        }

        $this->newLine();
        $this->table(['username', 'status', 'api_ms', 'job_ms', 'followers', 'error'], $rows);

        $ok = array_values(array_filter($rows, fn ($r) => $r['status'] === 'success'));
        if ($ok !== []) {
            $apiTimes = array_map('intval', array_column($ok, 'api_ms'));
            $summary = sprintf(
                'OK: %d/%d · api min %d ms · max %d ms · avg %d ms · bucket left %.1f/%d',
                count($ok),
                count($rows),
                min($apiTimes),
                max($apiTimes),
                (int) round(array_sum($apiTimes) / count($apiTimes)),
                $bucket->snapshot()['tokens_remaining'],
                $bucket->snapshot()['capacity'],
            );

            $jobTimes = array_values(array_filter(array_map(
                fn ($r) => is_int($r['job_ms']) ? $r['job_ms'] : null,
                $ok
            )));

            if ($jobTimes !== []) {
                $summary .= sprintf(
                    ' · job min %d ms · max %d ms · avg %d ms',
                    min($jobTimes),
                    max($jobTimes),
                    (int) round(array_sum($jobTimes) / count($jobTimes)),
                );
            }

            $this->info($summary);
        }

        return self::SUCCESS;
    }

    private function resetJobGuards(): void
    {
        Redis::del('api:token-bucket');
        Redis::del('circuit:apify:failures');
        Redis::del('circuit:apify:open_until');
        Redis::del('circuit:apify:half_open');
    }
}
