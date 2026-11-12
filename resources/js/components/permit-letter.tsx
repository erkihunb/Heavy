import { FileText } from 'lucide-react';
import { DualDate } from '@/components/dual-date';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import type { Permit } from '@/types';

/**
 * The letter a request came in with: its number, its date and a link that
 * opens the scanned copy in a new tab.
 */
export function PermitLetter({
    letter,
}: {
    letter: NonNullable<Permit['letter']>;
}) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="letter-title"
            className="space-y-3 rounded-card border border-border bg-card p-5"
        >
            <h3 id="letter-title" className="text-section-title">
                {t('permits.letter.title')}
            </h3>
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                <dt className="text-muted-foreground">
                    {t('permits.letter.number')}
                </dt>
                <dd className="font-semibold" translate="no">
                    {letter.number}
                </dd>
                <dt className="text-muted-foreground">
                    {t('permits.letter.date')}
                </dt>
                <dd>{letter.date ? <DualDate date={letter.date} /> : '—'}</dd>
            </dl>
            {letter.url && (
                <Button asChild variant="outline" size="sm">
                    <a href={letter.url} target="_blank" rel="noopener">
                        <FileText aria-hidden="true" />
                        {t('permits.letter.open')}
                    </a>
                </Button>
            )}
        </section>
    );
}
