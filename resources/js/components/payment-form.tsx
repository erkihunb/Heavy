import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/permits/payments';
import type { Money } from '@/types';
import type { PaymentMethod } from '@/types/enums';

type PaymentFormData = {
    method: PaymentMethod | '';
    receipt_number: string;
    amount: string;
    /** Gregorian ISO date; the date picker converts from the Ethiopian calendar. */
    paid_at: string;
    receipt_file: File | null;
    note: string;
};

/**
 * The applicant reports the fee of an approved permit as paid: how, the
 * receipt number, the amount, the date and a scan of the receipt. A cashier
 * uses the same form to record a payment taken at the desk (`forCashier`):
 * the scan is then optional and an internal note can be added. The server
 * checks everything again, including that the amount is exactly the fee.
 */
export function PaymentForm({
    permitId,
    fee,
    methods,
    maxKilobytes,
    action,
    forCashier = false,
}: {
    permitId: string;
    fee: Money;
    methods: PaymentMethod[];
    maxKilobytes: number;
    /** Where to send it; the applicant's own address unless a Registrar hands it in. */
    action?: string;
    /** A cashier records the payment: scan optional, internal note, amount filled in. */
    forCashier?: boolean;
}) {
    const { t } = useTranslation();
    const maxMegabytes = Math.floor(maxKilobytes / 1024);

    const form = useForm<PaymentFormData>({
        method: methods.length === 1 ? methods[0] : '',
        receipt_number: '',
        amount: forCashier ? (fee.amount_cents / 100).toFixed(2) : '',
        paid_at: '',
        receipt_file: null,
        note: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(action ?? store.url({ permit: permitId }), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            encType="multipart/form-data"
            className="space-y-6"
        >
            <fieldset
                aria-describedby={
                    form.errors.method ? 'method-error' : undefined
                }
                className="space-y-2"
            >
                <legend className="mb-2 text-sm leading-none font-medium">
                    {t('payments.fields.method')}
                </legend>
                <div className="grid gap-3 sm:grid-cols-2">
                    {methods.map((method) => (
                        <label
                            key={method}
                            className="flex min-h-12 cursor-pointer items-center gap-3 rounded-card border-2 border-input px-4 py-3 font-bold has-checked:border-primary has-checked:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                        >
                            <input
                                type="radio"
                                name="method"
                                value={method}
                                checked={form.data.method === method}
                                onChange={() => form.setData('method', method)}
                                className="size-5 accent-primary"
                            />
                            {t(`payments.methods.${method}`)}
                        </label>
                    ))}
                </div>
                <InputError
                    id="method-error"
                    role="alert"
                    message={form.errors.method}
                />
            </fieldset>

            <div className="grid gap-6 md:grid-cols-2">
                <FormField
                    id="receipt_number"
                    label={t('payments.fields.receipt_number')}
                    error={form.errors.receipt_number}
                >
                    {(control) => (
                        <Input
                            {...control}
                            value={form.data.receipt_number}
                            autoComplete="off"
                            translate="no"
                            maxLength={64}
                            onChange={(event) =>
                                form.setData(
                                    'receipt_number',
                                    event.target.value,
                                )
                            }
                        />
                    )}
                </FormField>

                <FormField
                    id="amount"
                    label={t('payments.fields.amount')}
                    hint={t('payments.form.amount_hint', {
                        fee: fee.formatted,
                    })}
                    error={form.errors.amount}
                >
                    {(control) => (
                        <Input
                            {...control}
                            value={form.data.amount}
                            inputMode="decimal"
                            autoComplete="off"
                            className="tabular-nums"
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                    )}
                </FormField>
            </div>

            <EthiopianDateInput
                id="paid_at"
                label={t('payments.fields.paid_at')}
                value={form.data.paid_at}
                error={form.errors.paid_at}
                yearsBefore={1}
                yearsAfter={0}
                onChange={(iso) => form.setData('paid_at', iso)}
            />

            <FormField
                id="receipt_file"
                label={
                    forCashier
                        ? t('permits.staff.record_payment.receipt_file')
                        : t('payments.fields.receipt_file')
                }
                hint={t('payments.form.file_hint', { max: maxMegabytes })}
                error={form.errors.receipt_file}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="file"
                        accept="application/pdf,image/jpeg,image/png"
                        className="h-auto py-2"
                        onChange={(event) =>
                            form.setData(
                                'receipt_file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                )}
            </FormField>

            {forCashier && (
                <FormField
                    id="payment_note"
                    label={t('permits.staff.record_payment.note')}
                    error={form.errors.note}
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={2}
                            maxLength={1000}
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                    )}
                </FormField>
            )}

            <Button type="submit" size="lg" disabled={form.processing}>
                {form.processing && <Spinner />}
                {forCashier
                    ? t('permits.staff.record_payment.submit')
                    : t('payments.form.submit')}
            </Button>
        </form>
    );
}
