<?php

namespace App\Jobs;

use App\Enums\ProfileStatus;
use App\Models\OpsEvent;
use App\Models\Profile;
use App\Services\ApiDailyQuota;
use App\Services\CircuitBreaker;
use App\Services\Ops\OpsEventRecorder;
use App\Services\ProfileFetchLocker;
use App\Services\ProfileProviders\ProfileProvider;
use App\Services\ProfileSnapshotWriter;
use App\Services\RetryClass;
use App\Services\RetryClassifier;
use App\Services\TokenBucketLimiter;
use App\Support\SafeExceptionMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class FetchProfileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $profileId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 120, 240, 480, 960];
    }

    public function handle(
        ProfileProvider $provider,
        ProfileFetchLocker $locker,
        TokenBucketLimiter $bucket,
        ApiDailyQuota $quota,
        CircuitBreaker $circuit,
        RetryClassifier $classifier,
        ProfileSnapshotWriter $writer,
        OpsEventRecorder $ops,
    ): void {
        $started = microtime(true);
        $opsEvent = $ops->start('job', 'FetchProfileJob', $this->profileId, [
            'attempt' => $this->attempts(),
            'job_id' => $this->job?->getJobId() ?? 'sync',
        ]);

        $profile = Profile::query()->find($this->profileId);

        if ($profile === null) {
            $this->finishOps($ops, $opsEvent, 'skipped', 'profile_missing', $started);

            return;
        }

        if (! $circuit->allowsRequest()) {
            $this->release(120);
            $this->finishOps($ops, $opsEvent, 'skipped', 'deferred_circuit_open', $started);

            return;
        }

        if (! $quota->allows(1)) {
            $this->release(300);
            $this->finishOps($ops, $opsEvent, 'skipped', 'deferred_daily_quota', $started, [
                'quota_used' => $quota->usedToday(),
                'quota_ceiling' => $quota->ceiling(),
                'quota_key' => $quota->keyForToday(),
            ]);

            return;
        }

        $bucketResult = $bucket->attempt();

        if (! $bucketResult['allowed']) {
            $delay = (int) (60 * (2 ** max(0, $this->attempts() - 1)));
            $jitter = random_int(0, 15);
            $this->release(min($delay + $jitter, 960));
            $this->finishOps($ops, $opsEvent, 'skipped', 'deferred_rate_limit', $started, [
                'tokens_consumed' => 0,
                'tokens_remaining' => $bucketResult['tokens_remaining'],
            ]);

            return;
        }

        $ran = $locker->attempt($profile->id, function () use ($profile, $provider, $writer, $circuit, $classifier, $started, $ops, $opsEvent, $bucketResult, $quota) {
            $profile->update([
                'status' => ProfileStatus::Fetching,
                'last_error' => null,
            ]);

            try {
                $apiStarted = microtime(true);
                $data = $provider->fetch($profile->username);
                $apiDurationMs = (int) ((microtime(true) - $apiStarted) * 1000);
                $quota->consume(1);
                $writer->write($profile->fresh(), $data);
                $circuit->recordSuccess();
                Redis::set('queue:last_processed_at', now()->timestamp);
                $this->finishOps($ops, $opsEvent, 'success', 'success', $started, [
                    'tokens_consumed' => $bucketResult['tokens_consumed'],
                    'tokens_remaining' => $bucketResult['tokens_remaining'],
                    'api_duration_ms' => $apiDurationMs,
                ]);
            } catch (Throwable $e) {
                $circuit->recordFailure();
                $class = $classifier->classify($e);

                if ($class === RetryClass::Fatal) {
                    $profile->fresh()?->update([
                        'status' => ProfileStatus::Failed,
                        'last_error' => SafeExceptionMessage::from($e),
                    ]);
                    $this->finishOps($ops, $opsEvent, 'failed', 'fatal', $started, [
                        'error' => SafeExceptionMessage::from($e),
                        'tokens_consumed' => $bucketResult['tokens_consumed'],
                        'tokens_remaining' => $bucketResult['tokens_remaining'],
                    ], 'error');
                    $this->fail($e);

                    return;
                }

                $this->finishOps($ops, $opsEvent, 'failed', 'retriable_failure', $started, [
                    'error' => SafeExceptionMessage::from($e),
                    'tokens_consumed' => $bucketResult['tokens_consumed'],
                    'tokens_remaining' => $bucketResult['tokens_remaining'],
                ], 'warning');
                throw $e;
            }
        });

        if (! $ran) {
            $this->finishOps($ops, $opsEvent, 'skipped', 'skipped_lock', $started, [
                'tokens_consumed' => $bucketResult['tokens_consumed'],
                'tokens_remaining' => $bucketResult['tokens_remaining'],
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        $profile = Profile::query()->find($this->profileId);

        if ($profile === null) {
            return;
        }

        if ($profile->status !== ProfileStatus::Failed) {
            $profile->update([
                'status' => ProfileStatus::Failed,
                'last_error' => $e ? SafeExceptionMessage::from($e) : 'Job failed after max attempts',
            ]);
        } elseif ($e !== null && ($profile->last_error === null || str_contains($profile->last_error, 'apify_api_'))) {
            $profile->update([
                'last_error' => SafeExceptionMessage::from($e),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     */
    private function finishOps(
        OpsEventRecorder $ops,
        OpsEvent $opsEvent,
        string $status,
        string $outcome,
        float $started,
        array $extraMeta = [],
        ?string $level = null,
    ): void {
        $durationMs = (int) ((microtime(true) - $started) * 1000);

        $payload = [
            'job_id' => $this->job?->getJobId() ?? 'sync',
            'profile_id' => $this->profileId,
            'attempt' => $this->attempts(),
            'duration_ms' => $durationMs,
            'outcome' => $outcome,
        ];

        $level ??= match (true) {
            $outcome === 'success' => 'info',
            str_starts_with($outcome, 'deferred'), str_starts_with($outcome, 'skipped'), $outcome === 'retriable_failure', $outcome === 'profile_missing' => 'warning',
            default => 'error',
        };

        Log::{$level}(json_encode($payload, JSON_THROW_ON_ERROR));

        $tokensConsumed = (int) ($extraMeta['tokens_consumed'] ?? 0);
        $tokensRemaining = array_key_exists('tokens_remaining', $extraMeta)
            ? (isset($extraMeta['tokens_remaining']) ? (float) $extraMeta['tokens_remaining'] : null)
            : null;

        unset($extraMeta['tokens_consumed']);

        $ops->finish(
            $opsEvent,
            $status,
            array_merge([
                'outcome' => $outcome,
                'attempt' => $this->attempts(),
                'job_id' => $payload['job_id'],
                'job_duration_ms' => $durationMs,
            ], $extraMeta),
            $tokensConsumed,
            $tokensRemaining,
        );
    }
}
