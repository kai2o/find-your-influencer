<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\Ops\OpsEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RefreshStaleProfilesCommand extends Command
{
    protected $signature = 'profiles:refresh-stale';

    protected $description = 'Enqueue FetchProfileJob for profiles older than 1 hour';

    public function handle(OpsEventRecorder $ops): int
    {
        $event = $ops->start('scheduler', 'profiles:refresh-stale');

        $lock = Cache::lock('profiles:refresh-stale', 540);

        if (! $lock->get()) {
            $this->info('Previous refresh still running; skipping.');
            $ops->finish($event, 'skipped', [
                'outcome' => 'skipped_overlap',
                'dispatched_count' => 0,
            ]);
            $ops->pruneOlderThanDays((int) config('ops.retention_days', 7));

            return self::SUCCESS;
        }

        $count = 0;

        try {
            $query = Profile::query()
                ->where(function ($q) {
                    $q->whereNull('last_refreshed_at')
                        ->orWhere('last_refreshed_at', '<', now('UTC')->subHour());
                })
                ->where('status', '!=', ProfileStatus::Fetching->value);

            $query->orderBy('id')->chunkById(100, function ($profiles) use (&$count) {
                foreach ($profiles as $profile) {
                    FetchProfileJob::dispatch($profile->id);
                    $count++;
                }
            });

            $this->info("Dispatched {$count} refresh jobs.");
            $ops->finish($event, 'success', [
                'outcome' => 'success',
                'dispatched_count' => $count,
            ]);
        } catch (Throwable $e) {
            $ops->finish($event, 'failed', [
                'outcome' => 'error',
                'error' => $e->getMessage(),
                'dispatched_count' => $count,
            ]);

            throw $e;
        } finally {
            $lock->release();
        }

        $ops->pruneOlderThanDays((int) config('ops.retention_days', 7));

        return self::SUCCESS;
    }
}
