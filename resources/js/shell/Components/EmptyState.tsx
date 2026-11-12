import type { ReactNode } from 'react';
import { useLocale } from '@/shell/hooks/useLocale';
import { EmptyState as UiEmptyState } from '@app/ui';

interface Props {
    title?: string;
    description?: string;
    action?: ReactNode;
    icon?: ReactNode;
    className?: string;
}

export default function EmptyState({ title, ...props }: Props) {
    const { t } = useLocale();
    return (
        <UiEmptyState
            title={title ?? t('shell.common.no_results')}
            {...props}
        />
    );
}
