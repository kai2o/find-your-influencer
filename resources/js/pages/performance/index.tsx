import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, Fragment, useState } from 'react';

type QueryRow = {
    id: number;
    sql: string;
    duration_ms: number;
    connection: string | null;
    captured_at: string | null;
};

type EventRow = {
    id: number;
    type: string;
    name: string;
    status: string;
    duration_ms: number | null;
    query_count: number;
    query_ms_total: number;
    tokens_consumed: number;
    tokens_remaining: number | null;
    profile_id: number | null;
    profile_username: string | null;
    correlation_id: string;
    meta: Record<string, unknown> | null;
    started_at: string | null;
    finished_at: string | null;
    queries: QueryRow[];
};

type PaginatedEvents = {
    data: EventRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

type Summary = {
    jobs_success: number;
    jobs_failed: number;
    jobs_skipped: number;
    api_calls: number;
    webhooks: number;
    scheduler_runs: number;
    avg_job_ms: number;
    query_ms_total: number;
    tokens_utilized: number;
    tokens_utilized_api: number;
    tokens_available: number;
    tokens_capacity: number;
    last_scheduler: {
        status: string;
        started_at: string | null;
        dispatched_count: number | null;
    } | null;
};

type Props = {
    summary: Summary;
    events: PaginatedEvents;
    filters: { type: string; status: string; profile_id: string | number };
    window: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Performance', href: '/performance' }];

function formatIst(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat('en-IN', {
        timeZone: 'Asia/Kolkata',
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    }).format(date);
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'success':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'skipped':
            return 'secondary';
        default:
            return 'outline';
    }
}

export default function PerformanceIndex({ summary, events, filters, window }: Props) {
    const [expanded, setExpanded] = useState<number | null>(null);
    const { data, setData, get, processing } = useForm({
        type: filters.type,
        status: filters.status,
        profile_id: filters.profile_id ? String(filters.profile_id) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        get('/performance', { preserveState: true });
    };

    const availablePct =
        summary.tokens_capacity > 0
            ? Math.min(100, Math.round((summary.tokens_available / summary.tokens_capacity) * 100))
            : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Performance" />
            <div className="flex flex-col gap-8 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Performance</h1>
                    <p className="text-muted-foreground text-sm">
                        Ops timeline for scheduler runs, queue jobs, provider API calls, webhooks, token use, and SQL
                        captured during those runs ({window}).
                    </p>
                </div>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-xs uppercase tracking-wide">Tokens utilized (24h)</div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">{summary.tokens_utilized.toLocaleString()}</div>
                        <div className="text-muted-foreground mt-1 text-xs">
                            Rate-limit bucket · API units {summary.tokens_utilized_api.toLocaleString()}
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-xs uppercase tracking-wide">Tokens available</div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {summary.tokens_available.toFixed(1)}
                            <span className="text-muted-foreground text-sm font-normal"> / {summary.tokens_capacity}</span>
                        </div>
                        <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                            <div className="bg-foreground h-full rounded-full transition-all" style={{ width: `${availablePct}%` }} />
                        </div>
                        <div className="text-muted-foreground mt-1 text-xs">{availablePct}% remaining · refill 10/min</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-xs uppercase tracking-wide">Jobs (24h)</div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {summary.jobs_success}
                            <span className="text-muted-foreground text-sm font-normal"> ok</span>
                        </div>
                        <div className="text-muted-foreground mt-1 text-xs">
                            {summary.jobs_failed} failed · {summary.jobs_skipped} skipped · avg {summary.avg_job_ms} ms
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-xs uppercase tracking-wide">Scheduler</div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">{summary.scheduler_runs}</div>
                        <div className="text-muted-foreground mt-1 text-xs">
                            {summary.last_scheduler
                                ? `Last ${summary.last_scheduler.status} at ${formatIst(summary.last_scheduler.started_at)} · dispatched ${summary.last_scheduler.dispatched_count ?? 0}`
                                : 'No runs yet'}
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-xs uppercase tracking-wide">API + webhooks</div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">{summary.api_calls}</div>
                        <div className="text-muted-foreground mt-1 text-xs">{summary.webhooks} webhook events</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-muted-foreground text-xs uppercase tracking-wide">SQL time (24h)</div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">{summary.query_ms_total.toLocaleString()} ms</div>
                        <div className="text-muted-foreground mt-1 text-xs">Sum of query_ms_total on ops events</div>
                    </div>
                </section>

                <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="type">Type</Label>
                        <select
                            id="type"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                        >
                            <option value="">All</option>
                            <option value="scheduler">scheduler</option>
                            <option value="job">job</option>
                            <option value="api">api</option>
                            <option value="webhook">webhook</option>
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                        >
                            <option value="">All</option>
                            <option value="running">running</option>
                            <option value="success">success</option>
                            <option value="failed">failed</option>
                            <option value="skipped">skipped</option>
                        </select>
                    </div>
                    <Button type="submit" disabled={processing} variant="secondary">
                        Filter
                    </Button>
                </form>

                <section>
                    <div className="mb-3">
                        <h2 className="text-lg font-medium">Event timeline</h2>
                        <p className="text-muted-foreground text-sm">
                            Scheduler ticks every 10 minutes; dispatches only if a profile is older than 1 hour. Expand a
                            row for SQL and timing breakdown (job vs API ms).
                        </p>
                    </div>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 border-b">
                                <tr>
                                    <th className="px-3 py-2.5 font-medium">When (IST)</th>
                                    <th className="px-3 py-2.5 font-medium">Type</th>
                                    <th className="px-3 py-2.5 font-medium">Name</th>
                                    <th className="px-3 py-2.5 font-medium">Status</th>
                                    <th className="px-3 py-2.5 font-medium">Duration</th>
                                    <th className="px-3 py-2.5 font-medium">Dispatched</th>
                                    <th className="px-3 py-2.5 font-medium">Tokens</th>
                                    <th className="px-3 py-2.5 font-medium">Queries</th>
                                    <th className="px-3 py-2.5 font-medium">Profile</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.data.map((event) => {
                                    const open = expanded === event.id;
                                    return (
                                        <Fragment key={event.id}>
                                            <tr
                                                className="hover:bg-muted/30 cursor-pointer border-b"
                                                onClick={() => setExpanded(open ? null : event.id)}
                                            >
                                                <td className="px-3 py-2.5 whitespace-nowrap">{formatIst(event.started_at)}</td>
                                                <td className="px-3 py-2.5">
                                                    <Badge variant="outline">{event.type}</Badge>
                                                </td>
                                                <td className="px-3 py-2.5 font-mono text-xs">{event.name}</td>
                                                <td className="px-3 py-2.5">
                                                    <Badge variant={statusVariant(event.status)}>{event.status}</Badge>
                                                </td>
                                                <td className="px-3 py-2.5 tabular-nums">
                                                    {event.duration_ms !== null ? `${event.duration_ms} ms` : '—'}
                                                </td>
                                                <td className="px-3 py-2.5 tabular-nums">
                                                    {event.type === 'scheduler'
                                                        ? (typeof event.meta?.dispatched_count === 'number'
                                                              ? event.meta.dispatched_count
                                                              : '—')
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2.5 tabular-nums">
                                                    {event.tokens_consumed > 0 ? (
                                                        <>
                                                            −{event.tokens_consumed}
                                                            {event.tokens_remaining !== null && (
                                                                <span className="text-muted-foreground">
                                                                    {' '}
                                                                    · left {Number(event.tokens_remaining).toFixed(1)}
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            0
                                                            {event.tokens_remaining !== null &&
                                                                ` · left ${Number(event.tokens_remaining).toFixed(1)}`}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 tabular-nums">
                                                    {event.query_count} · {event.query_ms_total} ms
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    {event.profile_username ? `@${event.profile_username}` : '—'}
                                                </td>
                                            </tr>
                                            {open && (
                                                <tr className="bg-muted/20 border-b">
                                                    <td colSpan={9} className="px-3 py-3">
                                                        <div className="mb-2 space-y-1 text-xs font-medium">
                                                            <div>
                                                                Outcome:{' '}
                                                                {typeof event.meta?.outcome === 'string'
                                                                    ? event.meta.outcome
                                                                    : '—'}
                                                                {event.meta?.http_status != null &&
                                                                    ` · HTTP ${String(event.meta.http_status)}`}
                                                                {` · tokens used ${event.tokens_consumed}`}
                                                                {event.tokens_remaining !== null &&
                                                                    ` · available after ${Number(event.tokens_remaining).toFixed(1)}`}
                                                            </div>
                                                            <div className="text-muted-foreground font-normal">
                                                                {event.type === 'scheduler' && (
                                                                    <>
                                                                        Dispatched jobs:{' '}
                                                                        {typeof event.meta?.dispatched_count === 'number'
                                                                            ? event.meta.dispatched_count
                                                                            : '—'}
                                                                        {event.meta?.outcome === 'skipped_overlap' &&
                                                                            ' (previous run still held the lock)'}
                                                                    </>
                                                                )}
                                                                {typeof event.meta?.job_duration_ms === 'number' && (
                                                                    <>Job {event.meta.job_duration_ms} ms</>
                                                                )}
                                                                {typeof event.meta?.api_duration_ms === 'number' && (
                                                                    <>
                                                                        {typeof event.meta?.job_duration_ms === 'number'
                                                                            ? ' · '
                                                                            : ''}
                                                                        API {event.meta.api_duration_ms} ms
                                                                    </>
                                                                )}
                                                                {event.meta?.benchmark === true && ' · benchmark'}
                                                            </div>
                                                        </div>
                                                        {event.queries.length === 0 ? (
                                                            <p className="text-muted-foreground text-xs">No SQL captured for this event.</p>
                                                        ) : (
                                                            <div className="max-h-64 space-y-2 overflow-y-auto">
                                                                {event.queries.map((q) => (
                                                                    <div key={q.id} className="rounded-md border bg-background p-2">
                                                                        <div className="text-muted-foreground mb-1 text-[11px]">
                                                                            {q.duration_ms} ms · {q.connection ?? 'default'} ·{' '}
                                                                            {formatIst(q.captured_at)}
                                                                        </div>
                                                                        <pre className="overflow-x-auto whitespace-pre-wrap font-mono text-[11px] leading-relaxed">
                                                                            {q.sql}
                                                                        </pre>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                                {events.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="text-muted-foreground px-3 py-10 text-center">
                                            No ops events yet. Re-fetch a profile or wait for the scheduler.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {events.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
