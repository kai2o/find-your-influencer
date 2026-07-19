import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type ProfileRow = {
    id: number;
    username: string;
    status: 'pending' | 'fetching' | 'fetched' | 'failed';
    followers_count: number | null;
    following_count: number | null;
    posts_count: number | null;
    last_refreshed_at: string | null;
    last_error: string | null;
};

type PaginatedProfiles = {
    data: ProfileRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
};

type Props = {
    profiles: PaginatedProfiles;
    filters: { q: string; status: string };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Watchlist', href: '/watchlist' }];

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

function statusVariant(status: ProfileRow['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
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

export default function WatchlistIndex({ profiles, filters }: Props) {
    const { data, setData, get, processing } = useForm({
        q: filters.q,
        status: filters.status,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        get('/watchlist', { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Watchlist" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Watchlist</h1>
                        <p className="text-muted-foreground text-sm">Tracked Instagram profiles and refresh status.</p>
                    </div>
                    <Button asChild>
                        <Link href="/watchlist/create">Add handle</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="q">Search</Label>
                        <Input
                            id="q"
                            value={data.q}
                            onChange={(e) => setData('q', e.target.value)}
                            placeholder="username"
                            className="w-56"
                        />
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
                            <option value="pending">pending</option>
                            <option value="fetching">fetching</option>
                            <option value="fetched">fetched</option>
                            <option value="failed">failed</option>
                        </select>
                    </div>
                    <Button type="submit" disabled={processing} variant="secondary">
                        Filter
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50 border-b">
                            <tr>
                                <th className="px-3 py-2 font-medium">Username</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Followers</th>
                                <th className="px-3 py-2 font-medium">Last refreshed (IST)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {profiles.data.map((profile) => (
                                <tr
                                    key={profile.id}
                                    className="hover:bg-muted/40 cursor-pointer border-b"
                                    onClick={() => router.visit(`/watchlist/${profile.id}`)}
                                >
                                    <td className="px-3 py-2 font-medium">@{profile.username}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={statusVariant(profile.status)}>{profile.status}</Badge>
                                    </td>
                                    <td className="px-3 py-2">{profile.followers_count?.toLocaleString() ?? '—'}</td>
                                    <td className="px-3 py-2">{formatIst(profile.last_refreshed_at)}</td>
                                </tr>
                            ))}
                            {profiles.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground px-3 py-8 text-center">
                                        No profiles yet. Add a handle to get started.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-2">
                    {profiles.links.map((link, i) => (
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
            </div>
        </AppLayout>
    );
}
