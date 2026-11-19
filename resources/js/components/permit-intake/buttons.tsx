import { Link } from '@inertiajs/react';
import { Send, UserPlus } from 'lucide-react';
import type { IntakeForm } from '@/components/permit-intake/form';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/permits';

/**
 * The form's buttons: save and send (or register only, when the request
 * part is switched off) and cancel. Stacked in the side panel,
 * in a row under the form.
 */
export type IntakeButtonsOptions = {
    /** The words on the save button (correcting a request). */
    submitLabel?: string;
    /** Where cancel goes (correcting a request: back to it). */
    cancelHref?: string;
};

export function IntakeButtons({
    form,
    stacked,
    submitLabel,
    cancelHref,
}: {
    form: IntakeForm;
    stacked: boolean;
} & IntakeButtonsOptions) {
    const { t } = useTranslation();
    const { data } = form;

    return (
        <div
            className={cn(
                'flex gap-2',
                stacked ? 'flex-col' : 'flex-col-reverse sm:flex-row',
            )}
        >
            {data.apply ? (
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <Send aria-hidden="true" />
                    )}
                    {submitLabel ?? t('applicants.intake.submit')}
                </Button>
            ) : (
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <UserPlus aria-hidden="true" />
                    )}
                    {t('applicants.form.register_only')}
                </Button>
            )}
            <Button asChild variant="ghost" size={stacked ? 'default' : 'lg'}>
                <Link href={cancelHref ?? index()}>
                    {t('applicants.intake.cancel')}
                </Link>
            </Button>
        </div>
    );
}
