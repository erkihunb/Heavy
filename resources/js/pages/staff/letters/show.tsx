import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { useMemo, useState } from 'react';
import { LetterStatusBadge } from '@/components/letter-status-badge';
import { PaymentForm } from '@/components/payment-form';
import { PlateChip } from '@/components/plate-chip';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import PageHeader from '@/shell/Components/PageHeader';
import {
    cx,
    tableBodyClassName,
    tableCellClassName,
    tableClassName,
    tableHeadClassName,
    tableHeaderCellClassName,
    tableRowClassName,
} from '@/shell/Components/ui';
import { useConfirm } from '@/shell/hooks/useConfirm';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { Money } from '@/types';
import { PAYMENT_METHODS } from '@/types/enums';
import type { PermitStatus } from '@/types/enums';
import type { TranslationKey } from '@/types/translations';

type DualDate = { ethiopian_label: string; gregorian: string } | null;

type Row = {
    id: string;
    status: PermitStatus;
    number: string | null;
    plate: string;
    type: string | null;
    trailers: string[];
    fee: string | null;
    recommendation: string | null;
    undecided: boolean;
    url: string | null;
};

type Props = {
    letter: {
        id: string;
        number: string;
        date: DualDate;
        organization: string;
        registered_by: string | null;
        registered_at: DualDate;
        file_url: string | null;
        valid_from: DualDate;
        valid_to: DualDate;
        permit_type: string;
        route: string | null;
        reason: string;
    };
    permits: Row[];
    summary: {
        total: number;
        statuses: Record<string, number>;
        to_pay: number;
        to_pay_total: string;
        to_pay_money: Money;
        to_issue: number;
        /** Vehicles returned for changes, and how many this person may send again. */
        returned: number;
        resubmittable: number;
        /** What the latest return asked to correct. */
        return_note: string | null;
        issued: number;
    };
    can: {
        recommend: boolean;
        decide: boolean;
        return: boolean;
        resubmit: boolean;
        sendForReview: boolean;
        recordPayment: boolean;
        issue: boolean;
        download: boolean;
    };
    reviewable: string[];
    review: {
        sent: { name: string; answered: boolean }[];
        opinions: {
            reviewer: string;
            recommendation: string;
            note: string;
            vehicles: number;
            at: string | null;
        }[];
    };
    reviewers: { id: number; name: string }[];
    recommendations: string[];
    receiptMaxKilobytes: number;
    urls: {
        sendForReview: string;
        recommend: string;
        approve: string;
        reject: string;
        return: string;
        resubmit: string;
        payment: string;
        issue: string;
        documentsAm: string;
        documentsEn: string;
    };
};

const panel =
    'space-y-4 rounded-card border border-border bg-card p-5 shadow-xs';

/**
 * A request letter for several vehicles (LetterController::show): its
 * details, every vehicle with where its permit stands, and what the office
 * does to the chosen vehicles together — recommend, approve or refuse, then
 * one payment, issuing and the certificates.
 */
function LetterPage({
    letter,
    permits,
    summary,
    can,
    reviewable,
    review,
    reviewers,
    recommendations,
    receiptMaxKilobytes,
    urls,
}: Props) {
    const { t } = useTranslation();
    const { confirm } = useConfirm();
    // The person who decides chooses among every waiting vehicle; a reviewer
    // only among those sent to them.
    const waiting = useMemo(
        () =>
            can.decide || can.sendForReview
                ? permits.filter((row) => row.undecided).map((row) => row.id)
                : reviewable,
        [permits, reviewable, can.decide, can.sendForReview],
    );
    const [chosen, setChosen] = useState<string[]>([]);
    // Only vehicles still waiting for a decision can be chosen.
    const selected = chosen.filter((id) => waiting.includes(id));
    const [recommendation, setRecommendation] = useState(
        recommendations[0] ?? '',
    );
    const [assessment, setAssessment] = useState('');
    const [decisionNote, setDecisionNote] = useState('');
    const [askReviewers, setAskReviewers] = useState<number[]>([]);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const choosing =
        can.recommend || can.decide || can.sendForReview || can.return;

    const toggle = (id: string, on: boolean) =>
        setChosen((current) =>
            on
                ? [...current.filter((item) => item !== id), id]
                : current.filter((item) => item !== id),
        );

    const send = (url: string, data: Record<string, string | string[]>) =>
        router.post(url, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (failed) => setErrors(failed),
            onSuccess: () => {
                setErrors({});
                setChosen([]);
                setAssessment('');
                setDecisionNote('');
                setAskReviewers([]);
            },
        });

    const DECISIONS = {
        approve: {
            title: 'letters.decide.confirm_approve',
            label: 'letters.decide.approve',
            url: urls.approve,
        },
        reject: {
            title: 'letters.decide.confirm_reject',
            label: 'letters.decide.reject',
            url: urls.reject,
        },
        return: {
            title: 'letters.decide.confirm_return',
            label: 'letters.decide.return',
            url: urls.return,
        },
    } as const;

    async function decide(kind: keyof typeof DECISIONS) {
        const decision = DECISIONS[kind];
        const { confirmed } = await confirm({
            title: t(decision.title, { count: selected.length }),
            description: t('letters.decide.confirm_body'),
            confirmLabel: t(decision.label, { count: selected.length }),
            variant: kind === 'reject' ? 'danger' : 'default',
        });

        if (confirmed) {
            send(decision.url, {
                permits: selected,
                note: decisionNote,
            });
        }
    }

    const detail = (label: TranslationKey, value: React.ReactNode) => (
        <div className="space-y-0.5">
            <dt className="text-xs text-muted-foreground">{t(label)}</dt>
            <dd className="text-sm font-medium text-foreground">{value}</dd>
        </div>
    );

    const dual = (date: DualDate) =>
        date ? (
            <>
                {date.ethiopian_label}
                <span className="block text-xs font-normal text-muted-foreground">
                    {date.gregorian}
                </span>
            </>
        ) : (
            '—'
        );

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('letters.breadcrumb') }]}
            header={
                <PageHeader
                    title={t('letters.title', { number: letter.number })}
                    description={t('letters.intro')}
                />
            }
        >
            <Head title={t('letters.title', { number: letter.number })} />

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="min-w-0 space-y-6">
                    <section className={panel}>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {detail(
                                'letters.details.organization',
                                letter.organization,
                            )}
                            {detail('letters.details.date', dual(letter.date))}
                            {detail(
                                'letters.details.registered',
                                <>
                                    {letter.registered_by ?? '—'}
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        {letter.registered_at?.ethiopian_label}
                                    </span>
                                </>,
                            )}
                            {detail(
                                'letters.details.type',
                                t(
                                    `permits.types.${letter.permit_type}` as TranslationKey,
                                ),
                            )}
                            {detail(
                                'letters.details.period',
                                <>
                                    {letter.valid_from?.ethiopian_label} –{' '}
                                    {letter.valid_to?.ethiopian_label}
                                </>,
                            )}
                            {detail(
                                'letters.details.route',
                                letter.route ?? t('letters.details.any_route'),
                            )}
                        </dl>
                        <p className="text-sm text-foreground">
                            <span className="text-xs text-muted-foreground">
                                {t('letters.details.reason')}:{' '}
                            </span>
                            {letter.reason}
                        </p>
                        {letter.file_url && (
                            <a
                                href={letter.file_url}
                                target="_blank"
                                rel="noopener"
                                className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                            >
                                <FileText aria-hidden className="size-4" />
                                {t('letters.details.scan')}
                            </a>
                        )}
                    </section>

                    <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {(
                            [
                                ['letters.summary.vehicles', summary.total],
                                ['letters.summary.to_pay', summary.to_pay],
                                ['letters.summary.to_issue', summary.to_issue],
                                ['letters.summary.issued', summary.issued],
                            ] as [TranslationKey, number][]
                        ).map(([label, value]) => (
                            <div key={label} className={cx(panel, 'p-4')}>
                                <dt className="text-xs text-muted-foreground">
                                    {t(label)}
                                </dt>
                                <dd className="text-2xl font-semibold text-foreground tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    <section className={cx(panel, 'p-0')}>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                            <h2 className="text-section-title">
                                {t('letters.table.title')}
                            </h2>
                            {choosing && waiting.length > 0 && (
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <span className="font-medium tabular-nums">
                                        {t('letters.choose.selected', {
                                            count: selected.length,
                                            total: waiting.length,
                                        })}
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setChosen(waiting)}
                                    >
                                        {t('letters.choose.all')}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setChosen(
                                                waiting.filter(
                                                    (id) =>
                                                        !selected.includes(id),
                                                ),
                                            )
                                        }
                                    >
                                        {t('letters.choose.invert')}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setChosen([])}
                                    >
                                        {t('letters.choose.clear')}
                                    </Button>
                                </div>
                            )}
                        </div>
                        {choosing && waiting.length > 0 && (
                            <p className="px-5 pt-3 text-xs text-muted-foreground">
                                {t('letters.choose.hint')}
                            </p>
                        )}
                        <div className="overflow-x-auto">
                            <table
                                className={cx(
                                    tableClassName,
                                    'min-w-full text-left',
                                )}
                            >
                                <thead className={tableHeadClassName}>
                                    <tr>
                                        {choosing && (
                                            <th
                                                scope="col"
                                                className={cx(
                                                    tableHeaderCellClassName,
                                                    'w-px',
                                                )}
                                            >
                                                <Checkbox
                                                    aria-label={t(
                                                        'letters.table.select_all',
                                                    )}
                                                    disabled={
                                                        waiting.length === 0
                                                    }
                                                    checked={
                                                        waiting.length > 0 &&
                                                        selected.length ===
                                                            waiting.length
                                                    }
                                                    onCheckedChange={(on) =>
                                                        setChosen(
                                                            on === true
                                                                ? waiting
                                                                : [],
                                                        )
                                                    }
                                                />
                                            </th>
                                        )}
                                        {(
                                            [
                                                'letters.table.plate',
                                                'letters.table.type',
                                                'letters.table.status',
                                                'letters.table.number',
                                                'letters.table.fee',
                                                'letters.table.recommendation',
                                            ] as TranslationKey[]
                                        ).map((heading) => (
                                            <th
                                                key={heading}
                                                scope="col"
                                                className={cx(
                                                    tableHeaderCellClassName,
                                                    'text-left',
                                                )}
                                            >
                                                {t(heading)}
                                            </th>
                                        ))}
                                        <th scope="col">
                                            <span className="sr-only">
                                                {t('letters.table.open')}
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className={tableBodyClassName}>
                                    {permits.map((row) => (
                                        <tr
                                            key={row.id}
                                            className={tableRowClassName}
                                        >
                                            {choosing && (
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    {waiting.includes(
                                                        row.id,
                                                    ) && (
                                                        <Checkbox
                                                            aria-label={t(
                                                                'letters.table.select',
                                                                {
                                                                    plate: row.plate,
                                                                },
                                                            )}
                                                            checked={selected.includes(
                                                                row.id,
                                                            )}
                                                            onCheckedChange={(
                                                                on,
                                                            ) =>
                                                                toggle(
                                                                    row.id,
                                                                    on === true,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </td>
                                            )}
                                            <td className={tableCellClassName}>
                                                <PlateChip
                                                    plate={row.plate}
                                                    className="px-2 py-0.5 text-sm"
                                                />
                                                {row.trailers.length > 0 && (
                                                    <span className="mt-1 block text-xs text-muted-foreground">
                                                        +{' '}
                                                        {row.trailers.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                )}
                                            </td>
                                            <td className={tableCellClassName}>
                                                {row.type ?? '—'}
                                            </td>
                                            <td className={tableCellClassName}>
                                                <LetterStatusBadge
                                                    status={row.status}
                                                />
                                            </td>
                                            <td
                                                className={cx(
                                                    tableCellClassName,
                                                    'font-mono text-xs',
                                                )}
                                            >
                                                {row.number ?? '—'}
                                            </td>
                                            <td
                                                className={cx(
                                                    tableCellClassName,
                                                    'whitespace-nowrap tabular-nums',
                                                )}
                                            >
                                                {row.fee ?? '—'}
                                            </td>
                                            <td className={tableCellClassName}>
                                                {row.recommendation
                                                    ? t(
                                                          `permits.staff.recommended.${row.recommendation}` as TranslationKey,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td
                                                className={cx(
                                                    tableCellClassName,
                                                    'text-right',
                                                )}
                                            >
                                                {row.url && (
                                                    <Link
                                                        href={row.url}
                                                        className="text-sm font-medium text-primary hover:underline"
                                                    >
                                                        {t(
                                                            'letters.table.open',
                                                        )}
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {errors.permits && (
                            <p className="px-5 pb-4 text-sm text-destructive">
                                {errors.permits}
                            </p>
                        )}
                    </section>
                </div>

                <aside className="space-y-6 xl:sticky xl:top-20">
                    {(can.decide || can.sendForReview) && (
                        <section className={panel}>
                            <h2 className="text-section-title">
                                {t('letters.review.opinions')}
                            </h2>
                            {review.opinions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('letters.review.opinions_empty')}
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {review.opinions.map((opinion, index) => (
                                        <li
                                            key={index}
                                            className="space-y-1 border-b border-border pb-3 last:border-0 last:pb-0"
                                        >
                                            <p className="text-sm font-medium text-foreground">
                                                {opinion.reviewer}
                                                {' · '}
                                                {t(
                                                    `permits.staff.recommend.${opinion.recommendation}` as TranslationKey,
                                                )}
                                            </p>
                                            <p className="text-sm whitespace-pre-line text-foreground">
                                                {opinion.note}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('letters.review.vehicles', {
                                                    count: opinion.vehicles,
                                                })}
                                                {opinion.at
                                                    ? ' · ' + opinion.at
                                                    : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {review.sent.length > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.review.sent_to')}:{' '}
                                    {review.sent
                                        .map(
                                            (person) =>
                                                person.name +
                                                ' (' +
                                                t(
                                                    person.answered
                                                        ? 'letters.review.answered'
                                                        : 'letters.review.waiting',
                                                ) +
                                                ')',
                                        )
                                        .join(', ')}
                                </p>
                            )}
                        </section>
                    )}

                    {can.sendForReview && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.review.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.review.hint')}
                                </p>
                            </div>
                            {reviewers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('letters.review.none')}
                                </p>
                            ) : (
                                <fieldset className="space-y-2">
                                    <legend className="mb-1 text-sm font-medium text-foreground">
                                        {t('letters.review.reviewers')}
                                    </legend>
                                    {reviewers.map((person) => (
                                        <label
                                            key={person.id}
                                            className="flex items-center gap-2 text-sm text-foreground"
                                        >
                                            <Checkbox
                                                aria-label={person.name}
                                                checked={askReviewers.includes(
                                                    person.id,
                                                )}
                                                onCheckedChange={(on) =>
                                                    setAskReviewers(
                                                        (current) =>
                                                            on === true
                                                                ? [
                                                                      ...current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              person.id,
                                                                      ),
                                                                      person.id,
                                                                  ]
                                                                : current.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          person.id,
                                                                  ),
                                                    )
                                                }
                                            />
                                            {person.name}
                                        </label>
                                    ))}
                                    {errors.reviewers && (
                                        <p className="text-sm text-destructive">
                                            {errors.reviewers}
                                        </p>
                                    )}
                                </fieldset>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                disabled={
                                    busy ||
                                    selected.length === 0 ||
                                    askReviewers.length === 0
                                }
                                onClick={() =>
                                    send(urls.sendForReview, {
                                        permits: selected,
                                        reviewers: askReviewers.map(String),
                                    })
                                }
                            >
                                {t('letters.review.submit', {
                                    count: selected.length,
                                })}
                            </Button>
                        </section>
                    )}

                    {can.recommend && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.recommend.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.recommend.hint')}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="recommendation">
                                    {t('letters.recommend.recommendation')}
                                </Label>
                                <NativeSelect
                                    id="recommendation"
                                    value={recommendation}
                                    onChange={(event) =>
                                        setRecommendation(event.target.value)
                                    }
                                >
                                    {recommendations.map((value) => (
                                        <option key={value} value={value}>
                                            {t(
                                                `permits.staff.recommend.${value}` as TranslationKey,
                                            )}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="assessment">
                                    {t('letters.recommend.note')}
                                </Label>
                                <Textarea
                                    id="assessment"
                                    rows={3}
                                    maxLength={2000}
                                    value={assessment}
                                    aria-invalid={
                                        errors.note ? true : undefined
                                    }
                                    onChange={(event) =>
                                        setAssessment(event.target.value)
                                    }
                                />
                            </div>
                            <Button
                                type="button"
                                className="w-full"
                                disabled={
                                    busy ||
                                    selected.length === 0 ||
                                    assessment.trim() === ''
                                }
                                onClick={() =>
                                    send(urls.recommend, {
                                        permits: selected,
                                        recommendation,
                                        note: assessment,
                                    })
                                }
                            >
                                {t('letters.recommend.submit', {
                                    count: selected.length,
                                })}
                            </Button>
                        </section>
                    )}

                    {summary.returned > 0 && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.returned.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.returned.hint', {
                                        count: summary.returned,
                                    })}
                                </p>
                            </div>
                            {summary.return_note && (
                                <div className="space-y-1 rounded-md bg-muted p-3">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {t('letters.returned.note')}
                                    </p>
                                    <p className="text-sm whitespace-pre-line text-foreground">
                                        {summary.return_note}
                                    </p>
                                </div>
                            )}
                            {can.resubmit && (
                                <Button
                                    type="button"
                                    className="w-full"
                                    disabled={busy}
                                    onClick={() => send(urls.resubmit, {})}
                                >
                                    {t('letters.returned.submit', {
                                        count: summary.resubmittable,
                                    })}
                                </Button>
                            )}
                        </section>
                    )}

                    {(can.decide || can.return) && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.decide.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.decide.hint')}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="decision-note">
                                    {t('letters.decide.note')}
                                </Label>
                                <Textarea
                                    id="decision-note"
                                    rows={3}
                                    maxLength={2000}
                                    value={decisionNote}
                                    aria-describedby="decision-note-hint"
                                    onChange={(event) =>
                                        setDecisionNote(event.target.value)
                                    }
                                />
                                <p
                                    id="decision-note-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('letters.decide.note_reject')}
                                </p>
                                {errors.note && (
                                    <p className="text-sm text-destructive">
                                        {errors.note}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                {can.decide && (
                                    <Button
                                        type="button"
                                        disabled={busy || selected.length === 0}
                                        onClick={() => void decide('approve')}
                                    >
                                        {t('letters.decide.approve', {
                                            count: selected.length,
                                        })}
                                    </Button>
                                )}
                                {can.return && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            busy ||
                                            selected.length === 0 ||
                                            decisionNote.trim() === ''
                                        }
                                        onClick={() => void decide('return')}
                                    >
                                        {t('letters.decide.return', {
                                            count: selected.length,
                                        })}
                                    </Button>
                                )}
                                {can.decide && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={
                                            busy ||
                                            selected.length === 0 ||
                                            decisionNote.trim() === ''
                                        }
                                        onClick={() => void decide('reject')}
                                    >
                                        {t('letters.decide.reject', {
                                            count: selected.length,
                                        })}
                                    </Button>
                                )}
                            </div>
                        </section>
                    )}

                    {can.recordPayment && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.payment.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.payment.hint', {
                                        count: summary.to_pay,
                                        total: summary.to_pay_total,
                                    })}
                                </p>
                            </div>
                            <PaymentForm
                                fee={summary.to_pay_money}
                                methods={[...PAYMENT_METHODS]}
                                maxKilobytes={receiptMaxKilobytes}
                                action={urls.payment}
                                forCashier
                            />
                        </section>
                    )}

                    {can.issue && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.issue.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.issue.hint', {
                                        count: summary.to_issue,
                                    })}
                                </p>
                            </div>
                            <Button
                                type="button"
                                className="w-full"
                                disabled={busy}
                                onClick={() => send(urls.issue, {})}
                            >
                                {t('letters.issue.submit', {
                                    count: summary.to_issue,
                                })}
                            </Button>
                        </section>
                    )}

                    {can.download && (
                        <section className={panel}>
                            <div className="space-y-1">
                                <h2 className="text-section-title">
                                    {t('letters.documents.title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('letters.documents.hint')}
                                </p>
                            </div>
                            <div className="grid gap-2">
                                {(
                                    [
                                        [
                                            urls.documentsAm,
                                            'letters.documents.am',
                                        ],
                                        [
                                            urls.documentsEn,
                                            'letters.documents.en',
                                        ],
                                    ] as [string, TranslationKey][]
                                ).map(([href, label]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        className="inline-flex items-center justify-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
                                    >
                                        <Download
                                            aria-hidden
                                            className="size-4"
                                        />
                                        {t(label)}
                                    </a>
                                ))}
                            </div>
                        </section>
                    )}
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}

/** useConfirm() runs in the page, above the layout's own provider, so the page brings one. */
export default function LetterShow(props: Props) {
    return (
        <ConfirmProvider>
            <LetterPage {...props} />
        </ConfirmProvider>
    );
}
