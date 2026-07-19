<?php

namespace App\Http\Controllers;

use App\Models\OpsEvent;
use App\Services\TokenBucketLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function index(Request $request, TokenBucketLimiter $bucket): Response
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:scheduler,job,api,webhook'],
            'status' => ['nullable', 'string', 'in:running,success,failed,skipped'],
            'profile_id' => ['nullable', 'integer', 'exists:profiles,id'],
        ]);

        $since = now('UTC')->subDay();

        $byType = OpsEvent::query()
            ->where('started_at', '>=', $since)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $byStatus = OpsEvent::query()
            ->where('started_at', '>=', $since)
            ->where('type', 'job')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $avgJobMs = (int) round((float) OpsEvent::query()
            ->where('started_at', '>=', $since)
            ->where('type', 'job')
            ->whereNotNull('duration_ms')
            ->avg('duration_ms'));

        $queryMsTotal = (int) OpsEvent::query()
            ->where('started_at', '>=', $since)
            ->sum('query_ms_total');

        // Bucket tokens charged on jobs; API units recorded on api events.
        $tokensUtilizedJobs = (int) OpsEvent::query()
            ->where('started_at', '>=', $since)
            ->where('type', 'job')
            ->sum('tokens_consumed');

        $tokensUtilizedApi = (int) OpsEvent::query()
            ->where('started_at', '>=', $since)
            ->where('type', 'api')
            ->sum('tokens_consumed');

        $bucketSnap = $bucket->snapshot();

        $lastScheduler = OpsEvent::query()
            ->where('type', 'scheduler')
            ->orderByDesc('started_at')
            ->first();

        $events = OpsEvent::query()
            ->with([
                'profile:id,username',
                'queries' => fn ($q) => $q->orderBy('captured_at')->limit(40),
            ])
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['profile_id'] ?? null, fn ($q, $profileId) => $q->where('profile_id', $profileId))
            ->orderByDesc('started_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (OpsEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'name' => $event->name,
                'status' => $event->status,
                'duration_ms' => $event->duration_ms,
                'query_count' => $event->query_count,
                'query_ms_total' => $event->query_ms_total,
                'tokens_consumed' => $event->tokens_consumed,
                'tokens_remaining' => $event->tokens_remaining,
                'profile_id' => $event->profile_id,
                'profile_username' => $event->profile?->username,
                'correlation_id' => $event->correlation_id,
                'meta' => $event->meta,
                'started_at' => $event->started_at?->timezone('Asia/Kolkata')->toIso8601String(),
                'finished_at' => $event->finished_at?->timezone('Asia/Kolkata')->toIso8601String(),
                'queries' => $event->queries->map(fn ($query) => [
                    'id' => $query->id,
                    'sql' => $query->sql,
                    'duration_ms' => $query->duration_ms,
                    'connection' => $query->connection,
                    'captured_at' => $query->captured_at?->timezone('Asia/Kolkata')->toIso8601String(),
                ])->values(),
            ]);

        return Inertia::render('performance/index', [
            'summary' => [
                'jobs_success' => (int) ($byStatus['success'] ?? 0),
                'jobs_failed' => (int) ($byStatus['failed'] ?? 0),
                'jobs_skipped' => (int) ($byStatus['skipped'] ?? 0),
                'api_calls' => (int) ($byType['api'] ?? 0),
                'webhooks' => (int) ($byType['webhook'] ?? 0),
                'scheduler_runs' => (int) ($byType['scheduler'] ?? 0),
                'avg_job_ms' => $avgJobMs,
                'query_ms_total' => $queryMsTotal,
                'tokens_utilized' => $tokensUtilizedJobs,
                'tokens_utilized_api' => $tokensUtilizedApi,
                'tokens_available' => $bucketSnap['tokens_remaining'],
                'tokens_capacity' => $bucketSnap['capacity'],
                'last_scheduler' => $lastScheduler ? [
                    'status' => $lastScheduler->status,
                    'started_at' => $lastScheduler->started_at?->timezone('Asia/Kolkata')->toIso8601String(),
                    'dispatched_count' => $lastScheduler->meta['dispatched_count'] ?? null,
                ] : null,
            ],
            'events' => $events,
            'filters' => [
                'type' => $validated['type'] ?? '',
                'status' => $validated['status'] ?? '',
                'profile_id' => $validated['profile_id'] ?? '',
            ],
            'window' => 'Last 24 hours',
        ]);
    }
}
