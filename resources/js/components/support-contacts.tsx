import { usePage } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * The support e-mail and phone an Admin set (System settings → General), as
 * in the EUISIS footer. Nothing is shown when neither is set.
 */
export function SupportContacts({ className }: { className?: string }) {
    const { t } = useTranslation();
    const branding = usePage().props.branding;
    const email = branding?.supportEmail;
    const phone = branding?.supportPhone;

    if (!email && !phone) {
        return null;
    }

    const link =
        'inline-flex items-center gap-1.5 rounded-xs font-semibold text-current underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none';

    return (
        <p
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-1',
                className,
            )}
        >
            <span>{t('common.support')}:</span>
            {email && (
                <a href={`mailto:${email}`} className={link} translate="no">
                    <Mail aria-hidden="true" className="size-3.5" />
                    {email}
                </a>
            )}
            {phone && (
                <a
                    href={`tel:${phone.replace(/[^\d+]/g, '')}`}
                    className={cn(link, 'tabular-nums')}
                    translate="no"
                >
                    <Phone aria-hidden="true" className="size-3.5" />
                    {phone}
                </a>
            )}
        </p>
    );
}
