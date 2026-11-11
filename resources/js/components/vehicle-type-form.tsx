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

export type VehicleTypeFormData = {
    code: string;
    name_am: string;
    name_en: string;
    is_active: boolean;
    sort_order: string;
};

/**
 * The fields of a vehicle type. The code is typed only when the type is
 * created; it cannot change afterwards.
 */
export function VehicleTypeForm({
    form,
    onSubmit,
    cancelHref,
    codeLocked,
}: {
    form: InertiaFormProps<VehicleTypeFormData>;
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
                label={t('admin.vehicle_types.fields.code')}
                hint={t('admin.vehicle_types.fields.code_hint')}
                error={form.errors.code}
            >
                {(control) => (
                    <Input
                        {...control}
                        translate="no"
                        autoComplete="off"
                        className="font-mono"
                        value={form.data.code}
                        readOnly={codeLocked}
                        aria-readonly={codeLocked || undefined}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                    />
                )}
            </FormField>

            <FormField
                id="name_am"
                label={t('admin.vehicle_types.fields.name_am')}
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
                label={t('admin.vehicle_types.fields.name_en')}
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

            <FormField
                id="sort_order"
                label={t('admin.vehicle_types.fields.sort_order')}
                error={form.errors.sort_order}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={1000}
                        className="w-32 tabular-nums"
                        value={form.data.sort_order}
                        onChange={(event) =>
                            form.setData('sort_order', event.target.value)
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
                        {t('admin.vehicle_types.fields.is_active')}
                    </Label>
                    <p
                        id="is_active-hint"
                        className="text-sm text-muted-foreground"
                    >
                        {t('admin.vehicle_types.fields.is_active_hint')}
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
                    {t('admin.vehicle_types.save')}
                </Button>
                <Button asChild size="lg" variant="ghost">
                    <Link href={cancelHref}>{t('common.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
