import { Link } from '@inertiajs/react';
import { Save, Send, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import type { IntakeForm } from '@/components/permit-intake/form';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/permits';

/** Saves the form: submitted for review, or kept as a draft. */
export type SaveIntake = (submitNow: boolean) => (event?: FormEvent) => void;

/**
 * The form's buttons: submit and save as a draft (or register only, when
 * the request part is switched off) and cancel. Stacked in the side panel,
 * in a row under the form.
 */
export function IntakeButtons({
    form,
    stacked,
    save,
}: {
    form: IntakeForm;
    stacked: boolean;
    save: SaveIntake;
}) {
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
                <>
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <Send aria-hidden="true" />
                        )}
                        {t('applicants.intake.submit')}
                    </Button>
                    <Button
                        type="button"
                        size="lg"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => save(false)()}
                    >
                        <Save aria-hidden="true" />
                        {t('applicants.intake.save_draft')}
                    </Button>
                </>
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
                <Link href={index()}>{t('applicants.intake.cancel')}</Link>
            </Button>
        </div>
    );
}
