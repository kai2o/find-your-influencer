import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { pushToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef } from 'react';

type Profile = {
    id: number;
    username: string;
    status: string;
    bio: string | null;
    profile_picture_url: string | null;
    followers_count: number | null;
    following_count: number | null;
    posts_count: number | null;
    last_refreshed_at: string | null;
    last_error: string | null;
};

type Snapshot = {
    id: number;
    followers_count: number;
    following_count: number | null;
    posts_count: number | null;
    followers_delta: number | null;
    captured_at: string;
};

type Performance = {
    window_days: number;
    snapshot_count: number;
    period_net_delta: number | null;
    largest_gain: number | null;
    largest_drop: number | null;
    avg_delta: number | null;
};

type Props = {
    profile: Profile;
    snapshots: Snapshot[];
    performance: Performance;
};

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
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(date);
}

function formatDelta(delta: number | null): { text: string; className: string } {
    if (delta === null || Number.isNaN(delta)) {
        return { text: '—', className: 'text-muted-foreground' };
    }
    if (delta > 0) {
        return { text: `+${Math.round(delta).toLocaleString()}`, className: 'text-emerald-500' };
    }
    if (delta < 0) {
        return { text: Math.round(delta).toLocaleString(), className: 'text-red-500' };
    }
    return { text: '0', className: 'text-muted-foreground' };
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'fetched':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'fetching':
            return 'secondary';
        default:
            return 'outline';
    }
}

function FollowersChart({ snapshots }: { snapshots: Snapshot[] }) {
    const points = useMemo(() => [...snapshots].reverse(), [snapshots]);

    if (points.length < 2) {
        return (
            <div className="text-muted-foreground flex h-56 items-center justify-center text-sm">
                Need at least two snapshots to chart follower trend.
            </div>
        );
    }

    const width = 720;
    const height = 220;
    const padX = 16;
    const padY = 20;
    const values = points.map((p) => p.followers_count);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = Math.max(max - min, 1);

    const coords = points.map((p, i) => {
        const x = padX + (i / (points.length - 1)) * (width - padX * 2);
        const y = height - padY - ((p.followers_count - min) / range) * (height - padY * 2);
        return { x, y, p };
    });

    const line = coords.map((c, i) => `${i === 0 ? 'M' : 'L'} ${c.x.toFixed(1)} ${c.y.toFixed(1)}`).join(' ');
    const area = `${line} L ${coords[coords.length - 1].x.toFixed(1)} ${height - padY} L ${coords[0].x.toFixed(1)} ${height - padY} Z`;

    return (
        <div className="w-full overflow-x-auto">
            <svg viewBox={`0 0 ${width} ${height}`} className="h-56 w-full min-w-[320px]" role="img" aria-label="Followers trend">
                <defs>
                    <linearGradient id="followersFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="currentColor" stopOpacity="0.28" />
                        <stop offset="100%" stopColor="currentColor" stopOpacity="0.02" />
                    </linearGradient>
                </defs>
                <path d={area} fill="url(#followersFill)" className="text-foreground" />
                <path d={line} fill="none" stroke="currentColor" strokeWidth="2.5" className="text-foreground" />
                {coords.map((c) => (
                    <circle key={c.p.id} cx={c.x} cy={c.y} r="3.5" className="fill-background stroke-foreground" strokeWidth="2" />
                ))}
                <text x={padX} y={14} className="fill-muted-foreground text-[11px]">
                    {max.toLocaleString()}
                </text>
                <text x={padX} y={height - 4} className="fill-muted-foreground text-[11px]">
                    {min.toLocaleString()}
                </text>
            </svg>
        </div>
    );
}

export default function WatchlistShow({ profile, snapshots, performance }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Watchlist', href: '/watchlist' },
        { title: `@${profile.username}`, href: `/watchlist/${profile.id}` },
    ];

    const previousStatus = useRef(profile.status);
    const toastedFailure = useRef(false);

    useEffect(() => {
        const inFlight = profile.status === 'pending' || profile.status === 'fetching';

        if (!inFlight) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['profile', 'snapshots', 'performance'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [profile.status, profile.id]);

    useEffect(() => {
        const prev = previousStatus.current;
        previousStatus.current = profile.status;

        if (prev === profile.status) {
            return;
        }

        if (profile.status === 'fetched' && (prev === 'pending' || prev === 'fetching')) {
            pushToast(`@${profile.username} fetched successfully.`, 'success');
            toastedFailure.current = false;
        }

        if (profile.status === 'failed' && (prev === 'pending' || prev === 'fetching') && !toastedFailure.current) {
            toastedFailure.current = true;
            pushToast(`Fetch failed for @${profile.username}. See the dashboard for details.`, 'error');
        }
    }, [profile.status, profile.username]);

    const refetch = () => {
        toastedFailure.current = false;
        router.post(`/watchlist/${profile.id}/refetch`);
    };

    const net = formatDelta(performance.period_net_delta);
    const gain = formatDelta(performance.largest_gain);
    const drop = formatDelta(performance.largest_drop);
    const avg = formatDelta(performance.avg_delta === null ? null : Number(performance.avg_delta));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Performance · @${profile.username}`} />
            <div className="flex flex-col gap-8 p-4 md:p-6">
                <section className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        {profile.profile_picture_url ? (
                            <img
                                src={profile.profile_picture_url}
                                alt={profile.username}
                                className="size-20 rounded-full object-cover ring-1 ring-border"
                            />
                        ) : (
                            <div className="bg-muted size-20 rounded-full ring-1 ring-border" />
                        )}
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-3xl font-semibold tracking-tight">@{profile.username}</h1>
                                <Badge variant={statusVariant(profile.status)}>{profile.status}</Badge>
                            </div>
                            <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">{profile.bio ?? 'No bio yet.'}</p>
                            <p className="text-muted-foreground text-xs">
                                Last refreshed (IST): <span className="text-foreground">{formatIst(profile.last_refreshed_at)}</span>
                            </p>
                            {(profile.status === 'pending' || profile.status === 'fetching') && (
                                <p className="text-muted-foreground text-sm">Fetch in progress — dashboard updates automatically…</p>
                            )}
                        </div>
                    </div>
                    <Button onClick={refetch} disabled={profile.status === 'fetching'}>
                        Re-fetch now
                    </Button>
                </section>

                {profile.status === 'failed' && profile.last_error && (
                    <section className="border-destructive/40 bg-destructive/10 rounded-xl border px-4 py-3">
                        <h2 className="text-destructive text-sm font-semibold">Fetch failed</h2>
                        <p className="text-destructive/90 mt-1 text-sm break-words">{profile.last_error}</p>
                        <p className="text-muted-foreground mt-2 text-xs">Fix the underlying issue, then use Re-fetch now.</p>
                    </section>
                )}

                <section>
                    <div className="mb-3">
                        <h2 className="text-lg font-medium">Current metrics</h2>
                        <p className="text-muted-foreground text-sm">Latest values stored on the profile.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="bg-muted/30 rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Followers</div>
                            <div className="mt-1 text-3xl font-semibold tabular-nums">{profile.followers_count?.toLocaleString() ?? '—'}</div>
                        </div>
                        <div className="bg-muted/30 rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Following</div>
                            <div className="mt-1 text-3xl font-semibold tabular-nums">{profile.following_count?.toLocaleString() ?? '—'}</div>
                        </div>
                        <div className="bg-muted/30 rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Posts</div>
                            <div className="mt-1 text-3xl font-semibold tabular-nums">{profile.posts_count?.toLocaleString() ?? '—'}</div>
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="text-lg font-medium">Performance ({performance.window_days} days)</h2>
                        <p className="text-muted-foreground text-sm">
                            {performance.snapshot_count} snapshot{performance.snapshot_count === 1 ? '' : 's'} in this window.
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Net change</div>
                            <div className={`mt-1 text-2xl font-semibold tabular-nums ${net.className}`}>{net.text}</div>
                        </div>
                        <div className="rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Largest gain</div>
                            <div className={`mt-1 text-2xl font-semibold tabular-nums ${gain.className}`}>{gain.text}</div>
                        </div>
                        <div className="rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Largest drop</div>
                            <div className={`mt-1 text-2xl font-semibold tabular-nums ${drop.className}`}>{drop.text}</div>
                        </div>
                        <div className="rounded-xl border p-4">
                            <div className="text-muted-foreground text-xs uppercase tracking-wide">Avg delta / refresh</div>
                            <div className={`mt-1 text-2xl font-semibold tabular-nums ${avg.className}`}>{avg.text}</div>
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border p-4">
                    <div className="mb-2">
                        <h2 className="text-lg font-medium">Follower trend</h2>
                        <p className="text-muted-foreground text-sm">Chronological followers across the last {performance.window_days} days.</p>
                    </div>
                    <FollowersChart snapshots={snapshots} />
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="text-lg font-medium">Snapshot history</h2>
                        <p className="text-muted-foreground text-sm">Every refresh with per-snapshot follower delta.</p>
                    </div>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 border-b">
                                <tr>
                                    <th className="px-3 py-2.5 font-medium">Captured (IST)</th>
                                    <th className="px-3 py-2.5 font-medium">Followers</th>
                                    <th className="px-3 py-2.5 font-medium">Delta</th>
                                    <th className="px-3 py-2.5 font-medium">Following</th>
                                    <th className="px-3 py-2.5 font-medium">Posts</th>
                                </tr>
                            </thead>
                            <tbody>
                                {snapshots.map((s) => {
                                    const delta = formatDelta(s.followers_delta);
                                    return (
                                        <tr key={s.id} className="border-b last:border-0">
                                            <td className="px-3 py-2.5 whitespace-nowrap">{formatIst(s.captured_at)}</td>
                                            <td className="px-3 py-2.5 tabular-nums">{s.followers_count.toLocaleString()}</td>
                                            <td className={`px-3 py-2.5 font-medium tabular-nums ${delta.className}`}>{delta.text}</td>
                                            <td className="px-3 py-2.5 tabular-nums">{s.following_count?.toLocaleString() ?? '—'}</td>
                                            <td className="px-3 py-2.5 tabular-nums">{s.posts_count?.toLocaleString() ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                                {snapshots.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-3 py-10 text-center">
                                            No snapshots yet. Re-fetch to capture the first performance point.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
