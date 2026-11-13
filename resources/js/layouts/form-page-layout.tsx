import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Crumb } from '@/shell/Components/Breadcrumbs';
import PageHeader from '@/shell/Components/PageHeader';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { ContentWidth } from '@/shell/Layouts/AuthenticatedLayout';

/**
 * The frame of every create and edit page, as the shell's Users and Roles
 * forms: the heading band with the breadcrumbs, a link back, the title and
 * one sentence on what the page is for; below it the form at the forms'
 * common width (or the full width, for a form with a side panel).
 * The fields go in FormSection cards and end with FormActions
 * (components/form-layout.tsx).
 */
export default function FormPageLayout({
    title,
    description,
    backHref,
    breadcrumbs,
    width = 'form',
    children,
}: {
    title: string;
    description?: string;
    backHref: string;
    /** A trail after home, for a page the menu does not lead to directly. */
    breadcrumbs?: Crumb[];
    width?: ContentWidth;
    children: ReactNode;
}) {
    return (
        <AuthenticatedLayout
            contentWidth={width}
            breadcrumbs={breadcrumbs}
            header={
                <PageHeader
                    backHref={backHref}
                    title={title}
                    description={description}
                />
            }
        >
            <Head title={title} />
            {children}
        </AuthenticatedLayout>
    );
}
