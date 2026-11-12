import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';

export type LetterValue = {
    letter_number: string;
    /** Gregorian ISO date; the picker converts from the Ethiopian calendar. */
    letter_date: string;
    letter_file: File | null;
};

/**
 * The letter a request came in with: its reference number, its date and a
 * scan (PDF or image). The server checks the file again.
 */
export function LetterFields({
    value,
    errors,
    onChange,
    hasStoredScan = false,
}: {
    value: LetterValue;
    errors: Partial<Record<string, string>>;
    onChange: (next: Partial<LetterValue>) => void;
    /** An edit: the scan already attached is kept unless a new one is chosen. */
    hasStoredScan?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <fieldset className="space-y-4 rounded-card border border-border bg-muted/30 p-4">
            <legend className="px-1 text-sm font-semibold">
                {t('permits.letter.title')}
            </legend>
            <p className="text-sm text-muted-foreground">
                {t('permits.letter.intro')}
            </p>
            <div className="grid gap-4 md:grid-cols-2">
                <FormField
                    id="letter_number"
                    label={t('permits.letter.number')}
                    error={errors.letter_number}
                >
                    {(control) => (
                        <Input
                            {...control}
                            value={value.letter_number}
                            maxLength={64}
                            autoComplete="off"
                            translate="no"
                            onChange={(event) =>
                                onChange({ letter_number: event.target.value })
                            }
                        />
                    )}
                </FormField>
                <EthiopianDateInput
                    id="letter_date"
                    label={t('permits.letter.date')}
                    value={value.letter_date}
                    error={errors.letter_date}
                    yearsBefore={1}
                    yearsAfter={0}
                    onChange={(iso) => onChange({ letter_date: iso })}
                />
            </div>
            <FormField
                id="letter_file"
                label={t('permits.letter.file')}
                hint={
                    hasStoredScan
                        ? t('permits.letter.file_keep')
                        : t('permits.letter.file_hint')
                }
                error={errors.letter_file}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="file"
                        accept="application/pdf,image/jpeg,image/png"
                        className="h-auto py-2"
                        onChange={(event) =>
                            onChange({
                                letter_file: event.target.files?.[0] ?? null,
                            })
                        }
                    />
                )}
            </FormField>
        </fieldset>
    );
}
