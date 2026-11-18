import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/i18n';

export type ChecklistRole = { id: number; label: string; users: number };

/**
 * The roles two-factor authentication is required for (System settings ›
 * Security), as in EUISIS: a checkbox per role with how many people hold it.
 * When it is required for everyone, every box shows checked and is locked.
 */
export function RoleChecklist({
    id,
    label,
    hint,
    roles,
    value,
    allRequired,
    error,
    onChange,
}: {
    id: string;
    label: string;
    hint?: string;
    roles: ChecklistRole[];
    value: string[];
    allRequired: boolean;
    error?: string;
    onChange: (value: string[]) => void;
}) {
    const { t } = useTranslation();
    const [filter, setFilter] = useState('');
    const needle = filter.trim().toLowerCase();
    const shown = needle
        ? roles.filter((role) => role.label.toLowerCase().includes(needle))
        : roles;

    const toggle = (role: number, checked: boolean) => {
        const key = String(role);

        onChange(
            checked
                ? [...value.filter((item) => item !== key), key]
                : value.filter((item) => item !== key),
        );
    };

    return (
        <fieldset className="space-y-3" aria-describedby={`${id}-hint`}>
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <legend className="text-sm font-medium">{label}</legend>
                <span className="text-xs text-muted-foreground">
                    {t('admin.settings.roles.selected').replace(
                        ':count',
                        String(allRequired ? roles.length : value.length),
                    )}
                </span>
            </div>
            <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                {allRequired ? t('admin.settings.roles.all_required') : hint}
            </p>

            {roles.length > 8 && (
                <Input
                    type="search"
                    value={filter}
                    onChange={(event) => setFilter(event.target.value)}
                    placeholder={t('admin.settings.roles.filter')}
                    aria-label={t('admin.settings.roles.filter')}
                />
            )}

            {roles.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('admin.settings.roles.none')}
                </p>
            ) : shown.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('admin.settings.roles.no_match')}
                </p>
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2">
                    {shown.map((role) => {
                        const box = `${id}-${role.id}`;

                        return (
                            <li
                                key={role.id}
                                className="flex items-center gap-3 rounded-md border border-border px-3 py-2"
                            >
                                <Checkbox
                                    id={box}
                                    checked={
                                        allRequired ||
                                        value.includes(String(role.id))
                                    }
                                    disabled={allRequired}
                                    onCheckedChange={(checked) =>
                                        toggle(role.id, checked === true)
                                    }
                                />
                                <Label
                                    htmlFor={box}
                                    className="flex flex-1 flex-wrap items-baseline justify-between gap-2"
                                >
                                    <span>{role.label}</span>
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {t(
                                            'admin.settings.roles.users',
                                        ).replace(':count', String(role.users))}
                                    </span>
                                </Label>
                            </li>
                        );
                    })}
                </ul>
            )}
            <InputError message={error} />
        </fieldset>
    );
}
