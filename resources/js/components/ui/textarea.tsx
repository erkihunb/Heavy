import * as React from 'react';

import { cn } from '@/lib/utils';

/** A multi-line text field, styled like Input. */
function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'flex min-h-24 w-full min-w-0 rounded-md border border-input bg-card px-3 py-2 text-base transition-[color,box-shadow,border-color] outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
