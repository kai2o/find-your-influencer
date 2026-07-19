<?php

namespace App\Services\Ops;

use App\Models\OpsEvent;
use App\Models\OpsQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OpsEventRecorder
{
    /** @var list<array{event: OpsEvent, queries: list<array{sql: string, duration_ms: int, connection: string|null, captured_at: \Carbon\CarbonInterface}>, started_at: float}> */
    private array $stack = [];

    private bool $listening = false;

    private bool $flushing = false;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function start(string $type, string $name, ?int $profileId = null, array $meta = []): OpsEvent
    {
        $event = OpsEvent::query()->create([
            'type' => $type,
            'name' => mb_substr($name, 0, 255),
            'status' => 'running',
            'profile_id' => $profileId,
            'correlation_id' => (string) Str::uuid(),
            'meta' => $meta === [] ? null : $meta,
            'query_count' => 0,
            'query_ms_total' => 0,
            'started_at' => now('UTC'),
        ]);

        $this->stack[] = [
            'event' => $event,
            'queries' => [],
            'started_at' => microtime(true),
        ];

        $this->ensureListening();

        return $event;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function finish(
        OpsEvent $event,
        string $status,
        array $meta = [],
        int $tokensConsumed = 0,
        ?float $tokensRemaining = null,
    ): void {
        $index = null;

        foreach ($this->stack as $i => $frame) {
            if ($frame['event']->id === $event->id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            $event->update([
                'status' => $status,
                'finished_at' => now('UTC'),
                'duration_ms' => $event->started_at
                    ? (int) max(0, $event->started_at->diffInMilliseconds(now('UTC')))
                    : null,
                'tokens_consumed' => max(0, $tokensConsumed),
                'tokens_remaining' => $tokensRemaining,
                'meta' => array_merge($event->meta ?? [], $meta),
            ]);

            return;
        }

        $frame = $this->stack[$index];
        array_splice($this->stack, $index, 1);

        $durationMs = (int) ((microtime(true) - $frame['started_at']) * 1000);
        $queries = $frame['queries'];
        $queryMsTotal = array_sum(array_column($queries, 'duration_ms'));

        $this->flushing = true;

        try {
            $event->update([
                'status' => $status,
                'duration_ms' => $durationMs,
                'finished_at' => now('UTC'),
                'query_count' => count($queries),
                'query_ms_total' => $queryMsTotal,
                'tokens_consumed' => max(0, $tokensConsumed),
                'tokens_remaining' => $tokensRemaining,
                'meta' => array_merge($event->meta ?? [], $meta),
            ]);

            if ($queries !== []) {
                $rows = array_map(fn (array $q) => [
                    'ops_event_id' => $event->id,
                    'sql' => $q['sql'],
                    'duration_ms' => $q['duration_ms'],
                    'connection' => $q['connection'],
                    'captured_at' => $q['captured_at'],
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ], $queries);

                foreach (array_chunk($rows, 100) as $chunk) {
                    OpsQuery::query()->insert($chunk);
                }
            }
        } catch (Throwable $e) {
            Log::warning('ops_event_finish_failed', [
                'event_id' => $event->id,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->flushing = false;
        }
    }

    public function pruneOlderThanDays(int $days = 7): int
    {
        $this->flushing = true;

        try {
            return OpsEvent::query()
                ->where('started_at', '<', now('UTC')->subDays($days))
                ->delete();
        } finally {
            $this->flushing = false;
        }
    }

    private function ensureListening(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        DB::listen(function (QueryExecuted $query): void {
            $this->captureQuery($query);
        });
    }

    private function captureQuery(QueryExecuted $query): void
    {
        if ($this->flushing || $this->stack === []) {
            return;
        }

        $sql = $query->sql;
        $normalized = strtolower($sql);

        if (str_contains($normalized, 'ops_events')
            || str_contains($normalized, 'ops_queries')
            || str_contains($normalized, 'system_events')
            || str_contains($normalized, 'information_schema')
            || str_contains($normalized, 'pg_catalog')) {
            return;
        }

        $top = count($this->stack) - 1;
        $clean = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        $this->stack[$top]['queries'][] = [
            'sql' => mb_substr($clean, 0, 2000),
            'duration_ms' => (int) round($query->time),
            'connection' => $query->connectionName,
            'captured_at' => now('UTC'),
        ];
    }
}
