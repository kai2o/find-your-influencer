import { pushToast, useToasts, type ToastItem } from '@/hooks/use-toast';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useRef } from 'react';

function ToastCard({ toast, onDismiss }: { toast: ToastItem; onDismiss: (id: number) => void }) {
    const isError = toast.variant === 'error';

    return (
        <div
            role="status"
            className={`flex max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg ${
                isError
                    ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
            }`}
        >
            <p className="flex-1 leading-snug">{toast.message}</p>
            <button
                type="button"
                aria-label="Dismiss"
                className="opacity-70 hover:opacity-100"
                onClick={() => onDismiss(toast.id)}
            >
                <X className="size-4" />
            </button>
        </div>
    );
}

export function FlashToasts() {
    const { flash } = usePage<SharedData>().props;
    const { toasts, dismiss } = useToasts();
    const seen = useRef<string>('');

    useEffect(() => {
        const key = `${flash?.success ?? ''}|${flash?.error ?? ''}`;
        if (!key || key === '|' || key === seen.current) {
            return;
        }
        seen.current = key;

        if (flash?.success) {
            pushToast(flash.success, 'success');
        }
        if (flash?.error) {
            pushToast(flash.error, 'error');
        }
    }, [flash?.success, flash?.error]);

    if (toasts.length === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed top-4 right-4 z-50 flex flex-col gap-2">
            {toasts.map((toast) => (
                <div key={toast.id} className="pointer-events-auto">
                    <ToastCard toast={toast} onDismiss={dismiss} />
                </div>
            ))}
        </div>
    );
}

export function DemoModeBanner() {
    const { app } = usePage<SharedData>().props;

    if (!app?.usingFakeData) {
        return null;
    }

    return (
        <div className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100 border-b px-4 py-2 text-center text-sm">
            Demo mode (fake Instagram data). Set <code className="rounded bg-black/5 px-1">APIFY_TOKEN</code> and{' '}
            <code className="rounded bg-black/5 px-1">PROFILE_PROVIDER=apify</code> in <code className="rounded bg-black/5 px-1">.env</code> for
            live fetches, then restart the queue worker.
        </div>
    );
}
