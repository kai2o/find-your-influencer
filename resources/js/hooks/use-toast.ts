import { useCallback, useEffect, useState } from 'react';

export type ToastVariant = 'success' | 'error';

export type ToastItem = {
    id: number;
    message: string;
    variant: ToastVariant;
};

let toastId = 0;
const listeners = new Set<(toast: ToastItem) => void>();

export function pushToast(message: string, variant: ToastVariant = 'success'): void {
    const toast: ToastItem = { id: ++toastId, message, variant };
    listeners.forEach((listener) => listener(toast));
}

export function useToastBus(onToast: (toast: ToastItem) => void): void {
    useEffect(() => {
        listeners.add(onToast);
        return () => {
            listeners.delete(onToast);
        };
    }, [onToast]);
}

export function useToasts(): {
    toasts: ToastItem[];
    dismiss: (id: number) => void;
    push: (message: string, variant?: ToastVariant) => void;
} {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const dismiss = useCallback((id: number) => {
        setToasts((current) => current.filter((t) => t.id !== id));
    }, []);

    const push = useCallback((message: string, variant: ToastVariant = 'success') => {
        pushToast(message, variant);
    }, []);

    const onToast = useCallback((toast: ToastItem) => {
        setToasts((current) => [...current, toast]);
        window.setTimeout(() => {
            setToasts((current) => current.filter((t) => t.id !== toast.id));
        }, 5000);
    }, []);

    useToastBus(onToast);

    return { toasts, dismiss, push };
}
