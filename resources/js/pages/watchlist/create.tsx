import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Watchlist', href: '/watchlist' },
    { title: 'Add', href: '/watchlist/create' },
];

export default function WatchlistCreate() {
    const { data, setData, post, processing, errors } = useForm({
        username: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/watchlist');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add handle" />
            <div className="mx-auto flex w-full max-w-lg flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Add Instagram handle</h1>
                    <p className="text-muted-foreground text-sm">We will queue a background fetch for public profile metrics.</p>
                </div>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            placeholder="@cristiano"
                            autoFocus
                        />
                        <InputError message={errors.username} />
                    </div>
                    <Button type="submit" disabled={processing} className="inline-flex items-center gap-2">
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {processing ? 'Adding…' : 'Add to watchlist'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
