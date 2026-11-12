import { Clock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ethiopianTime } from '@/lib/ethiopian-time';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type MovementHoursValue = {
    any_time: boolean;
    move_from: string;
    move_to: string;
};

/**
 * When in the day a permit lets its vehicles move: any time, or only between
 * two times (Addis Ababa time, 24-hour clock). Each time is also read on the
 * Ethiopian clock, as people say it (11:00 is "ከቀኑ 5:00"); a window may run
 * past midnight.
 */
export function MovementHoursField({
    value,
    onChange,
    errors,
    idPrefix = 'hours',
}: {
    value: MovementHoursValue;
    onChange: (next: Partial<MovementHoursValue>) => void;
    errors: Partial<Record<string, string>>;
    idPrefix?: string;
}) {
    const { t } = useTranslation();

    const ethiopian = (time: string) => {
        const reading = ethiopianTime(time);

        return reading
            ? t(`permits.hours.periods.${reading.period}`, {
                  time: reading.hour,
              })
            : null;
    };

    const overnight =
        !value.any_time &&
        value.move_from !== '' &&
        value.move_to !== '' &&
        value.move_from > value.move_to;

    const choice = (anyTime: boolean, label: string) => (
        <label
            className={cn(
                'flex min-h-11 cursor-pointer items-center gap-3 rounded-card border-2 border-input px-4 py-2 font-semibold transition-colors hover:border-primary/50',
                'has-checked:border-primary has-checked:bg-primary/5 has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
            )}
        >
            <input
                type="radio"
                name={`${idPrefix}_any_time`}
                checked={value.any_time === anyTime}
                onChange={() => onChange({ any_time: anyTime })}
                className="size-4 accent-primary"
            />
            {label}
        </label>
    );

    const time = (field: 'move_from' | 'move_to', label: string) => {
        const id = `${idPrefix}_${field}`;
        const reading = ethiopian(value[field]);

        return (
            <div className="grid gap-1.5">
                <Label htmlFor={id}>{label}</Label>
                <Input
                    id={id}
                    type="time"
                    step={300}
                    className="tabular-nums"
                    value={value[field]}
                    aria-invalid={errors[field] ? true : undefined}
                    aria-describedby={`${id}-ethiopian`}
                    onChange={(event) =>
                        onChange({ [field]: event.target.value })
                    }
                />
                <p
                    id={`${id}-ethiopian`}
                    className="flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Clock aria-hidden="true" className="size-3.5" />
                    {reading
                        ? t('permits.hours.ethiopian', { time: reading })
                        : '—'}
                </p>
                <InputError message={errors[field]} />
            </div>
        );
    };

    return (
        <fieldset className="space-y-4">
            <legend className="sr-only">{t('permits.hours.title')}</legend>
            <div className="grid gap-3 sm:grid-cols-2">
                {choice(true, t('permits.hours.any_time'))}
                {choice(false, t('permits.hours.limited'))}
            </div>

            {!value.any_time && (
                <div className="grid gap-5 sm:grid-cols-2">
                    {time('move_from', t('permits.hours.from'))}
                    {time('move_to', t('permits.hours.to'))}
                </div>
            )}

            {overnight && (
                <p className="text-sm text-muted-foreground">
                    {t('permits.hours.overnight')}
                </p>
            )}
            <InputError message={errors.any_time} />
        </fieldset>
    );
}
