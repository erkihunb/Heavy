import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';

export type BranchFormData = {
    code: string;
    name_am: string;
    name_en: string;
    is_active: boolean;
};

/**
 * The fields of a branch office. The code is typed only when the branch is
 * created: it is printed in permit numbers and cannot change afterwards.
 */
export function BranchForm({
    form,
    onSubmit,
    cancelHref,
    codeLocked,
}: {
    form: InertiaFormProps<BranchFormData>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    cancelHref: string;
    codeLocked: boolean;
}) {
    const { t } = useTranslation();

    return (
        <form
            onSubmit={onSubmit}
            noValidate
            className="max-w-xl space-y-5 rounded-card border border-border bg-card p-5"
        >
            <FormField
                id="code"
                label={t('admin.branches.fields.code')}
                hint={t('admin.branches.fields.code_hint')}
                error={form.errors.code}
            >
                {(control) => (
                    <Input
                        {...control}
                        translate="no"
                        autoComplete="off"
                        maxLength={6}
                        className="w-40 font-mono uppercase"
                        value={form.data.code}
                        readOnly={codeLocked}
                        aria-readonly={codeLocked || undefined}
                        onChange={(event) =>
                            form.setData(
                                'code',
                                event.target.value.toUpperCase(),
                            )
                        }
                    />
                )}
            </FormField>

            <FormField
                id="name_am"
                label={t('admin.branches.fields.name_am')}
                error={form.errors.name_am}
            >
                {(control) => (
                    <Input
                        {...control}
                        lang="am"
                        value={form.data.name_am}
                        onChange={(event) =>
                            form.setData('name_am', event.target.value)
                        }
                    />
                )}
            </FormField>

            <FormField
                id="name_en"
                label={t('admin.branches.fields.name_en')}
                error={form.errors.name_en}
            >
                {(control) => (
                    <Input
                        {...control}
                        lang="en"
                        value={form.data.name_en}
                        onChange={(event) =>
                            form.setData('name_en', event.target.value)
                        }
                    />
                )}
            </FormField>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_active"
                    checked={form.data.is_active}
                    aria-describedby="is_active-hint"
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                <div className="grid gap-1">
                    <Label htmlFor="is_active">
                        {t('admin.branches.fields.is_active')}
                    </Label>
                    <p
                        id="is_active-hint"
                        className="text-sm text-muted-foreground"
                    >
                        {t('admin.branches.fields.is_active_hint')}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap gap-3">
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <Save aria-hidden="true" />
                    )}
                    {t('admin.branches.save')}
                </Button>
                <Button asChild size="lg" variant="ghost">
                    <Link href={cancelHref}>{t('common.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
