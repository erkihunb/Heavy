import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';

/**
 * Shows the one-off message left by the previous request ("Vehicle
 * registered."). It is announced politely to screen readers.
 */
export function FlashMessage() {
    const { flash } = usePage().props;
    const message = flash.success ?? flash.warning;

    if (!message) {
        return null;
    }

    const isSuccess = flash.success !== null;
    const Icon = isSuccess ? CircleCheck : CircleAlert;

    return (
        <div className="mx-auto w-full max-w-6xl px-4 pt-4 sm:px-6 sm:pt-6">
            <p
                role="status"
                className={
                    isSuccess
                        ? 'flex animate-rise items-center gap-3 rounded-xl border-2 border-success bg-accent px-4 py-3 font-medium text-accent-foreground'
                        : 'flex animate-rise items-center gap-3 rounded-xl border border-border bg-muted px-4 py-3 font-medium'
                }
            >
                <Icon aria-hidden="true" className="size-5 shrink-0" />
                {message}
            </p>
        </div>
    );
}
