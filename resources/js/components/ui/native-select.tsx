import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A native <select>, styled like Input. Native is deliberate: it is the most
 * usable control on phones and needs no JavaScript to be accessible.
 */
function NativeSelect({
    className,
    children,
    ...props
}: React.ComponentProps<'select'>) {
    return (
        <select
            data-slot="native-select"
            className={cn(
                'flex h-9 pointer-coarse:h-11 w-full min-w-0 rounded-md border border-input bg-card px-3 py-1 text-base transition-[color,box-shadow,border-color] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                className,
            )}
            {...props}
        >
            {children}
        </select>
    );
}

export { NativeSelect };
