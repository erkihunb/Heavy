import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    EmptyRow,
    Table,
    TableCard,
    TBody,
    Td,
    Th,
    THead,
    Tr,
} from '@/components/data-table';
import { DateFilterField } from '@/components/date-filter-field';
import { DualDate } from '@/components/dual-date';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { PlateChip } from '@/components/plate-chip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { PERMIT_STATUS_GROUPS } from '@/lib/status-groups';
import { index, show } from '@/routes/staff/permits';
import { FilterField, FilterPanel, Input, Select } from '@/shell/Components/ui';
import type { BreadcrumbItem, Paginated, Permit } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { PermitStatus, PermitType } from '@/types/enums';
import type { TranslationKey } from '@/types/translations';

/** A row standing for a request letter's vehicles at one stage (PermitReviewController::letterPayments). */
type LetterPayment = {
    letter: string | null;
    vehicles: number;
    /** What to pay; null while the letter waits for a decision. */
    total: string | null;
    url: string;
    /** review: decide it; awaiting_payment: take its one payment; payment_confirmed: issue it. */
    stage: string;
};

/** What the row's button says, by the letter's stage. */
const LETTER_ACTION: Record<string, TranslationKey> = {
    review: 'letters.go_decide',
    awaiting_payment: 'letters.go_pay',
    payment_confirmed: 'letters.go_issue',
};

/** Waiting for a decision: a letter's vehicle is decided on the letter's page. */
const reviewStage = (status: PermitStatus) =>
    status === 'submitted' || status === 'under_review';

type Props = {
    permits: Paginated<Permit>;
    letterPayments: Record<string, LetterPayment>;
    filters: {
        /** null = waiting for review (the default), "all" = every status. */
        status: PermitStatus | 'all' | null;
        permit_type: PermitType | null;
        plate: string;
        organization: string;
        submitted_from: string | null;
        submitted_to: string | null;
    };
};

export default function StaffPermitsIndex({
    permits,
    letterPayments,
    filters,
}: Props) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<string>(filters.status ?? '');
    const [permitType, setPermitType] = useState<string>(
        filters.permit_type ?? '',
    );
    const [plate, setPlate] = useState(filters.plate);
    const [organization, setOrganization] = useState(filters.organization);
    const [submittedFrom, setSubmittedFrom] = useState(
        filters.submitted_from ?? '',
    );
    const [submittedTo, setSubmittedTo] = useState(filters.submitted_to ?? '');

    const filtered =
        filters.status !== null ||
        filters.permit_type !== null ||
        filters.plate !== '' ||
        filters.organization !== '' ||
        filters.submitted_from !== null ||
        filters.submitted_to !== null;

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            index.url(),
            {
                ...(status !== '' && { status }),
                ...(permitType !== '' && { permit_type: permitType }),
                ...(plate.trim() !== '' && { plate: plate.trim() }),
                ...(organization.trim() !== '' && {
                    organization: organization.trim(),
                }),
                ...(submittedFrom !== '' && { submitted_from: submittedFrom }),
                ...(submittedTo !== '' && { submitted_to: submittedTo }),
            },
            { preserveState: true, replace: true },
        );
    };

    const waiting = (hours: number) =>
        hours < 48
            ? t('permits.staff.waiting_hours', { count: hours })
            : t('permits.staff.waiting_days', {
                  count: Math.floor(hours / 24),
              });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.staff.queue_title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.staff.queue_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('permits.staff.queue_title')}
                    description={t('permits.staff.queue_intro')}
                />

                <FilterPanel
                    label={t('permits.staff.queue_title')}
                    onSubmit={search}
                    onReset={() => router.get(index.url())}
                    hasActiveFilters={filtered}
                    submitLabel={t('permits.filters.apply')}
                    resetLabel={t('permits.filters.clear')}
                >
                    <FilterField label={t('permits.filters.status')}>
                        <Select
                            id="filter-status"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">
                                {t('permits.filters.waiting_review')}
                            </option>
                            <option value="all">
                                {t('permits.filters.all_statuses')}
                            </option>
                            {PERMIT_STATUS_GROUPS.map((value) => (
                                <option key={value} value={value}>
                                    {t(`permits.status_group.${value}`)}
                                </option>
                            ))}
                        </Select>
                    </FilterField>
                    <FilterField label={t('permits.filters.permit_type')}>
                        <Select
                            id="filter-type"
                            value={permitType}
                            onChange={(event) =>
                                setPermitType(event.target.value)
                            }
                        >
                            <option value="">
                                {t('permits.filters.all_types')}
                            </option>
                            {PERMIT_TYPES.map((value) => (
                                <option key={value} value={value}>
                                    {t(`permits.types.${value}`)}
                                </option>
                            ))}
                        </Select>
                    </FilterField>
                    <FilterField label={t('permits.filters.plate')}>
                        <Input
                            id="filter-plate"
                            type="search"
                            value={plate}
                            maxLength={50}
                            onChange={(event) => setPlate(event.target.value)}
                        />
                    </FilterField>
                    <FilterField label={t('permits.filters.organization')}>
                        <Input
                            id="filter-organization"
                            type="search"
                            value={organization}
                            maxLength={100}
                            onChange={(event) =>
                                setOrganization(event.target.value)
                            }
                        />
                    </FilterField>
                    <DateFilterField>
                        <EthiopianDateInput
                            id="filter-submitted-from"
                            label={t('permits.filters.submitted_from')}
                            value={submittedFrom}
                            yearsBefore={3}
                            yearsAfter={0}
                            onChange={setSubmittedFrom}
                        />
                    </DateFilterField>
                    <DateFilterField>
                        <EthiopianDateInput
                            id="filter-submitted-to"
                            label={t('permits.filters.submitted_to')}
                            value={submittedTo}
                            yearsBefore={3}
                            yearsAfter={0}
                            onChange={setSubmittedTo}
                        />
                    </DateFilterField>
                </FilterPanel>

                <p
                    className="text-sm text-muted-foreground tabular-nums"
                    aria-live="polite"
                >
                    {permits.meta.total === 0
                        ? t('permits.staff.queue_empty')
                        : t('permits.staff.queue_count', {
                              count: permits.meta.total,
                          })}
                </p>

                <TableCard toolsId="review-queue">
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('permits.fields.status')}</Th>
                                <Th>{t('permits.filters.organization')}</Th>
                                <Th>{t('permits.fields.permit_type')}</Th>
                                <Th>{t('permits.fields.permit_number')}</Th>
                                <Th>{t('permits.fields.vehicles')}</Th>
                                <Th>{t('permits.fields.validity')}</Th>
                                <Th>{t('permits.fields.submitted')}</Th>
                                <Th align="right">
                                    {t('permits.fields.waiting')}
                                </Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {permits.data.length === 0 ? (
                                <EmptyRow colSpan={9}>
                                    {t('permits.staff.queue_empty')}
                                </EmptyRow>
                            ) : (
                                permits.data.map((permit) => (
                                    <Tr key={permit.id}>
                                        <Td>
                                            <StatusBadge
                                                status={permit.effective_status}
                                            />
                                        </Td>
                                        <Td className="font-medium">
                                            {permit.organization}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {t(
                                                `permits.types.${permit.permit_type}`,
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {permit.permit_number ? (
                                                <span
                                                    translate="no"
                                                    className="font-semibold tabular-nums"
                                                >
                                                    {permit.permit_number}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </Td>
                                        <Td>
                                            {letterPayments[permit.id] ? (
                                                <span className="text-sm font-medium">
                                                    {letterPayments[permit.id]
                                                        .total === null
                                                        ? t(
                                                              'letters.queue_row_review',
                                                              {
                                                                  letter:
                                                                      letterPayments[
                                                                          permit
                                                                              .id
                                                                      ]
                                                                          .letter ??
                                                                      '',
                                                                  count: letterPayments[
                                                                      permit.id
                                                                  ].vehicles,
                                                              },
                                                          )
                                                        : t(
                                                              'letters.queue_row',
                                                              {
                                                                  letter:
                                                                      letterPayments[
                                                                          permit
                                                                              .id
                                                                      ]
                                                                          .letter ??
                                                                      '',
                                                                  count: letterPayments[
                                                                      permit.id
                                                                  ].vehicles,
                                                                  total:
                                                                      letterPayments[
                                                                          permit
                                                                              .id
                                                                      ].total ??
                                                                      '',
                                                              },
                                                          )}
                                                </span>
                                            ) : (
                                                <span className="flex flex-wrap gap-1.5">
                                                    {(
                                                        permit.vehicles ?? []
                                                    ).map((vehicle) => (
                                                        <PlateChip
                                                            key={vehicle.id}
                                                            plate={
                                                                vehicle.plate
                                                            }
                                                        />
                                                    ))}
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <span className="flex items-start gap-x-2">
                                                <DualDate
                                                    stacked
                                                    date={permit.valid_from}
                                                />
                                                <span aria-hidden="true">
                                                    –
                                                </span>
                                                <DualDate
                                                    stacked
                                                    date={permit.valid_to}
                                                />
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {permit.submitted_at ? (
                                                <DualDate
                                                    stacked
                                                    date={permit.submitted_at}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </Td>
                                        <Td
                                            numeric
                                            className="font-semibold whitespace-nowrap"
                                        >
                                            {typeof permit.waiting_hours ===
                                            'number'
                                                ? waiting(permit.waiting_hours)
                                                : '—'}
                                        </Td>
                                        <Td align="right">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={
                                                        letterPayments[
                                                            permit.id
                                                        ]?.url ??
                                                        // A letter's vehicle is decided on the letter's page.
                                                        (reviewStage(
                                                            permit.status,
                                                        )
                                                            ? permit.letter
                                                                  ?.group_url
                                                            : undefined) ??
                                                        show({
                                                            permit: permit.id,
                                                        })
                                                    }
                                                >
                                                    {letterPayments[permit.id]
                                                        ? t(
                                                              LETTER_ACTION[
                                                                  letterPayments[
                                                                      permit.id
                                                                  ].stage
                                                              ] ??
                                                                  'letters.go_pay',
                                                          )
                                                        : t(
                                                              'permits.actions.view',
                                                          )}
                                                </Link>
                                            </Button>
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination links={permits.links} meta={permits.meta} />
            </div>
        </AppLayout>
    );
}
