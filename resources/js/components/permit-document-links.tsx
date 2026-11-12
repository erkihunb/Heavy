import { FileDown, Printer } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { printPdf } from '@/lib/print-pdf';
import { cn } from '@/lib/utils';
import type { PermitDocumentUrls } from '@/types';

type Language = keyof PermitDocumentUrls;

type Props = {
    urls: PermitDocumentUrls;
    size?: 'default' | 'lg';
    variant?: 'default' | 'outline';
    /** Stack the buttons full width (narrow sidebars) instead of side by side. */
    stacked?: boolean;
};

/**
 * The permit's PDFs, one per language: each can be downloaded, or printed
 * straight away without leaving the page.
 */
export function PermitDocumentLinks({
    urls,
    size = 'default',
    variant = 'default',
    stacked = false,
}: Props) {
    const { t } = useTranslation();
    const [printing, setPrinting] = useState<Language | null>(null);
    const [failed, setFailed] = useState(false);

    const links: {
        lang: Language;
        href: string;
        label: string;
        print: string;
    }[] = [
        {
            lang: 'am',
            href: urls.am,
            label: t('permits.actions.download_am'),
            print: t('permits.actions.print_am'),
        },
        {
            lang: 'en',
            href: urls.en,
            label: t('permits.actions.download_en'),
            print: t('permits.actions.print_en'),
        },
    ];

    const print = async (lang: Language, href: string) => {
        setFailed(false);
        setPrinting(lang);

        try {
            await printPdf(href);
        } catch {
            setFailed(true);
        } finally {
            setPrinting(null);
        }
    };

    return (
        <div className="flex shrink-0 flex-col gap-2">
            <div
                className={cn(
                    'flex gap-3',
                    stacked ? 'flex-col' : 'flex-col sm:flex-row',
                )}
            >
                {links.map((link, index) => (
                    <div key={link.lang} className="flex gap-2">
                        <Button
                            asChild
                            size={size}
                            variant={index === 0 ? variant : 'outline'}
                            className={cn(stacked && 'flex-1')}
                        >
                            <a
                                href={link.href}
                                target="_blank"
                                rel="noopener"
                                hrefLang={link.lang}
                            >
                                <FileDown aria-hidden="true" />
                                {link.label}
                            </a>
                        </Button>
                        <Button
                            type="button"
                            size={size}
                            variant="outline"
                            aria-label={link.print}
                            title={link.print}
                            disabled={printing !== null}
                            onClick={() => void print(link.lang, link.href)}
                        >
                            {printing === link.lang ? (
                                <Spinner />
                            ) : (
                                <Printer aria-hidden="true" />
                            )}
                            <span>
                                {printing === link.lang
                                    ? t('permits.actions.printing')
                                    : t('permits.actions.print')}
                            </span>
                        </Button>
                    </div>
                ))}
            </div>
            {failed && (
                <p role="alert" className="text-sm text-destructive-foreground">
                    {t('permits.actions.print_failed')}
                </p>
            )}
        </div>
    );
}
