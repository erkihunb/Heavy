import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentType } from 'react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** A lucide or Solar icon: anything that takes a className. */
    icon?: LucideIcon | ComponentType<{ className?: string }> | null;
    isActive?: boolean;
};
