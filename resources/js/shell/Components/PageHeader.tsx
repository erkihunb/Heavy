import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useLocale } from '@/shell/hooks/useLocale';
import { actionLinkClassName } from '@app/ui';

interface Props {
    title: string;
    description?: string;
    actions?: ReactNode;
    backHref?: string;
}

export default function PageHeader({
    title,
    description,
    actions,
    backHref,
}: Props) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
                {backHref && (
                    <Link
                        href={backHref}
                        className={actionLinkClassName({
                            tone: 'neutral',
                            className: 'mb-2 inline-flex items-center gap-1',
                        })}
                    >
                        <svg
                            className="h-3.5 w-3.5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fillRule="evenodd"
                                d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z"
                                clipRule="evenodd"
                            />
                        </svg>
                        {t('shell.common.back')}
                    </Link>
                )}
                <h1 className="text-page-title wrap-break-word text-(--app-foreground)">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm wrap-break-word text-(--app-muted-foreground)">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex w-full flex-wrap items-center gap-2 sm:ml-4 sm:w-auto sm:shrink-0 sm:flex-nowrap">
                    {actions}
                </div>
            )}
        </div>
    );
}
