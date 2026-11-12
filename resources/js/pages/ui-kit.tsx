import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import Dropdown from '@/shell/Components/Dropdown';
import RichTextEditor from '@/shell/Components/forms/RichTextEditor';
import RichTextViewer from '@/shell/Components/forms/RichTextViewer';
import FormSection from '@/shell/Components/FormSection';
import * as Icons from '@/shell/Components/Icons';
import Modal from '@/shell/Components/Modal';
import * as NavIcons from '@/shell/Components/NavIcons';
import PageHeader from '@/shell/Components/PageHeader';
import RoleBadge from '@/shell/Components/RoleBadge';
import StatCard from '@/shell/Components/StatCard';
import StatusBadge from '@/shell/Components/StatusBadge';
import {
    Alert,
    AppActionMenu,
    AppDataTable,
    AppDialog,
    AppFilterBar,
    AppMetricCard,
    AppMetricCardSkeleton,
    AppPageHeaderSkeleton,
    AppPageLoader,
    AppSkeleton,
    AppTabNav,
    Button,
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
    EmptyState,
    ErrorState,
    FileUpload,
    FilterField,
    FormField,
    Input,
    SearchableSelect,
    SearchInput,
    Select,
    Skeleton,
    Tabs,
    Textarea,
    Tooltip,
    badgeClassName,
    createColumnHelper,
} from '@/shell/Components/ui';
import type { AppDataTableMeta, Tone } from '@/shell/Components/ui';
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import { toast } from '@/shell/lib/toast';

/*
 * The UI kit demo (routes/ui-kit.php, development only): every component of
 * the kit ported from the source system, with sample data, in the signed-in shell.
 */

const PAGE = '/ui-kit';

type Row = {
    id: number;
    plate: string;
    owner: string;
    type: string;
    status: string;
    amount: number;
    submitted_at: string;
};

type Props = {
    rows: Row[];
    meta: AppDataTableMeta;
    filters: { search: string; status: string };
    statuses: string[];
    types: string[];
    tab: 'overview' | 'forms' | 'data';
};

const TONES: Tone[] = ['neutral', 'info', 'success', 'warning', 'danger'];
const VARIANTS = [
    'default',
    'primary',
    'secondary',
    'outline',
    'ghost',
    'destructive',
    'destructiveOutline',
    'link',
] as const;
const VARIANT_KEYS: Record<(typeof VARIANTS)[number], string> = {
    default: 'default',
    primary: 'primary',
    secondary: 'secondary',
    outline: 'outline',
    ghost: 'ghost',
    destructive: 'destructive',
    destructiveOutline: 'destructive_outline',
    link: 'link',
};
const ROLES = [
    'admin',
    'approver',
    'reviewer',
    'cashier',
    'registrar',
    'traffic_officer',
    'applicant',
];
const STATUS_SAMPLES = [
    'draft',
    'submitted',
    'under_review',
    'awaiting_payment',
    'issued',
    'active',
    'expired',
    'rejected',
    'revoked',
];
const SAMPLE_HTML =
    '<h2>Movement hours</h2><p>Heavy vehicles may enter the city <strong>between 22:00 and 05:00</strong> only.</p><ul><li>Carry the permit in the cab.</li><li>Show the QR code when asked.</li></ul><p>Questions: <a href="mailto:permits@example.test">permits@example.test</a></p>';

const column = createColumnHelper<Row>();

function Section({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section aria-labelledby={`${id}-title`} className="space-y-4">
            <h2
                id={`${id}-title`}
                className="text-section-title text-(--app-foreground)"
            >
                {title}
            </h2>
            {children}
        </section>
    );
}

function Row({ label, children }: { label?: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            {label && (
                <p className="text-xs font-medium text-(--app-muted-foreground)">
                    {label}
                </p>
            )}
            <div className="flex flex-wrap items-center gap-2">{children}</div>
        </div>
    );
}

export default function UiKit(props: Props) {
    return (
        <ConfirmProvider>
            <UiKitPage {...props} />
        </ConfirmProvider>
    );
}

function UiKitPage({ rows, meta, filters, statuses, types, tab }: Props) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [modalOpen, setModalOpen] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [tableLoading, setTableLoading] = useState(false);
    const [tableEmpty, setTableEmpty] = useState(false);
    const [quickSearch, setQuickSearch] = useState('');
    const [officer, setOfficer] = useState('');
    const [notice, setNotice] = useState(SAMPLE_HTML);

    const form = useForm<{
        name: string;
        email: string;
        phone: string;
        type: string;
        axles: string;
        notes: string;
        document: File | null;
    }>({
        name: '',
        email: '',
        phone: '',
        type: '',
        axles: '',
        notes: '',
        document: null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`${PAGE}/form`, {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    const ask = async (requireReason = false) => {
        const result = await confirm({
            title: t('shell.demo.overlays.confirm_title'),
            description: t('shell.demo.overlays.confirm_body'),
            variant: 'danger',
            requireReason,
        });
        if (result.confirmed)
            toast.success(
                t('shell.demo.overlays.confirmed'),
                result.reason ? { description: result.reason } : undefined,
            );
        else toast.info(t('shell.demo.overlays.cancelled'));
    };

    const columns = [
        column.accessor('plate', {
            header: t('shell.demo.table.plate'),
            cell: ({ getValue }) => (
                <span className="font-mono font-medium">{getValue()}</span>
            ),
        }),
        column.accessor('owner', { header: t('shell.demo.table.owner') }),
        column.accessor('type', { header: t('shell.demo.table.type') }),
        column.accessor('status', {
            header: t('shell.demo.table.status'),
            cell: ({ getValue }) => <StatusBadge status={getValue()} />,
        }),
        column.accessor('amount', {
            header: t('shell.demo.table.amount'),
            cell: ({ getValue }) => (
                <span className="tabular-nums">
                    {getValue().toLocaleString()}
                </span>
            ),
        }),
        column.accessor('submitted_at', {
            header: t('shell.demo.table.submitted'),
        }),
        column.display({
            id: 'actions',
            header: () => (
                <span className="sr-only">{t('shell.ui.actions')}</span>
            ),
            cell: ({ row }) => (
                <div className="text-right">
                    <AppActionMenu
                        items={[
                            {
                                label: t('shell.demo.table.view'),
                                icon: <Icons.EyeIcon className="h-4 w-4" />,
                                onClick: () => toast.info(row.original.plate),
                            },
                            {
                                label: t('shell.demo.table.edit'),
                                icon: <Icons.PencilIcon className="h-4 w-4" />,
                                onClick: () => toast.info(row.original.plate),
                            },
                            {
                                label: t('shell.demo.table.delete'),
                                icon: <Icons.TrashIcon className="h-4 w-4" />,
                                variant: 'danger',
                                separatorBefore: true,
                                onClick: () =>
                                    toast.warning(
                                        t('shell.demo.table.deleted'),
                                    ),
                            },
                        ]}
                    />
                </div>
            ),
        }),
    ];

    const iconGroups: {
        title: string;
        icons: [string, (props: { className?: string }) => ReactNode][];
    }[] = [
        {
            title: t('shell.demo.icons.content'),
            icons: Object.entries(Icons).filter(
                ([, value]) => typeof value === 'function',
            ) as never,
        },
        {
            title: t('shell.demo.icons.shell'),
            icons: Object.entries(NavIcons).filter(
                ([, value]) => typeof value === 'function',
            ) as never,
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('shell.demo.title')}
                    description={t('shell.demo.intro')}
                />
            }
            breadcrumbs={[{ label: t('shell.demo.title') }]}
        >
            <Head title={t('shell.demo.title')} />

            <div className="space-y-12">
                <Section
                    id="navigation"
                    title={t('shell.demo.sections.navigation')}
                >
                    <AppTabNav
                        label={t('shell.demo.nav.label')}
                        current={tab}
                        items={(['overview', 'forms', 'data'] as const).map(
                            (key) => ({
                                key,
                                label: t(`shell.demo.nav.${key}`),
                                href: PAGE,
                                data: { tab: key },
                                count: key === 'data' ? meta.total : undefined,
                            }),
                        )}
                    />
                    <Tabs
                        items={(['overview', 'forms', 'data'] as const).map(
                            (key) => ({
                                id: key,
                                label: t(`shell.demo.nav.${key}`),
                                content: (
                                    <p className="text-sm text-(--app-muted-foreground)">
                                        {t(`shell.demo.nav.${key}`)}
                                    </p>
                                ),
                            }),
                        )}
                    />
                </Section>

                <Section id="buttons" title={t('shell.demo.sections.buttons')}>
                    <Card className="space-y-5">
                        <Row label={t('shell.demo.buttons.variants')}>
                            {VARIANTS.map((variant) => (
                                <Button key={variant} variant={variant}>
                                    {t(
                                        `shell.demo.variants.${VARIANT_KEYS[variant]}`,
                                    )}
                                </Button>
                            ))}
                        </Row>
                        <Row label={t('shell.demo.buttons.sizes')}>
                            <Button size="xs">XS</Button>
                            <Button size="sm">
                                {t('shell.demo.buttons.small')}
                            </Button>
                            <Button>{t('shell.demo.variants.default')}</Button>
                            <Button size="lg">
                                {t('shell.demo.buttons.large')}
                            </Button>
                        </Row>
                        <Row label={t('shell.demo.buttons.states')}>
                            <Button>{t('shell.actions.save')}</Button>
                            <Button variant="outline">
                                {t('shell.actions.export')}
                            </Button>
                            <Button variant="destructiveOutline">
                                {t('shell.actions.delete')}
                            </Button>
                            <Button loading>
                                {t('shell.demo.buttons.loading')}
                            </Button>
                            <Button disabled>
                                {t('shell.demo.buttons.disabled')}
                            </Button>
                            <Button
                                variant="secondary"
                                icon={<Icons.DownloadIcon />}
                                iconPosition="right"
                            >
                                {t('shell.actions.download')}
                            </Button>
                        </Row>
                        <Row label={t('shell.demo.buttons.icon_only')}>
                            {(
                                [
                                    ['shell.actions.edit', Icons.PencilIcon],
                                    ['shell.actions.print', Icons.PrinterIcon],
                                    ['shell.actions.copy', Icons.ScrollText],
                                    ['shell.actions.delete', Icons.TrashIcon],
                                ] as const
                            ).map(([key, Icon]) => (
                                <Tooltip key={key} content={t(key)}>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        aria-label={t(key)}
                                    >
                                        <Icon
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </Tooltip>
                            ))}
                        </Row>
                    </Card>
                </Section>

                <Section id="badges" title={t('shell.demo.sections.badges')}>
                    <Card className="space-y-5">
                        <Row label={t('shell.demo.badges.tones')}>
                            {TONES.map((tone) => (
                                <span
                                    key={tone}
                                    className={badgeClassName(tone)}
                                >
                                    {t(`shell.demo.tones.${tone}`)}
                                </span>
                            ))}
                        </Row>
                        <Row label={t('shell.demo.badges.statuses')}>
                            {STATUS_SAMPLES.map((status) => (
                                <StatusBadge key={status} status={status} />
                            ))}
                        </Row>
                        <Row label={t('shell.demo.badges.roles')}>
                            {ROLES.map((role) => (
                                <RoleBadge key={role} role={role} />
                            ))}
                        </Row>
                    </Card>
                </Section>

                <Section id="cards" title={t('shell.demo.sections.cards')}>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <AppMetricCard
                            label={t('shell.demo.cards.active')}
                            value="1,284"
                            variant="primary"
                            icon={<Icons.BadgeCheckIcon className="h-5 w-5" />}
                            trend="up"
                            trendLabel="+8%"
                            detail={t('shell.demo.cards.vs_last_month')}
                        />
                        <AppMetricCard
                            label={t('shell.demo.cards.pending')}
                            value={37}
                            variant="warning"
                            icon={<Icons.ClockIcon className="h-5 w-5" />}
                            trend="neutral"
                            trendLabel="0%"
                        />
                        <AppMetricCard
                            label={t('shell.demo.cards.revenue')}
                            value="ETB 412,500"
                            variant="success"
                            icon={<Icons.ReceiptTextIcon className="h-5 w-5" />}
                            trend="up"
                            trendLabel="+12%"
                        />
                        <AppMetricCard
                            label={t('shell.demo.cards.rejected')}
                            value={9}
                            variant="danger"
                            icon={<Icons.XCircle className="h-5 w-5" />}
                            trend="down"
                            trendLabel="-3%"
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label={t('shell.demo.cards.active')}
                            value="1,284"
                            tone="primary"
                            icon={<Icons.BadgeCheckIcon className="h-5 w-5" />}
                            hint={t('shell.demo.cards.vs_last_month')}
                        />
                        <StatCard
                            label={t('shell.demo.cards.pending')}
                            value={37}
                            tone="warning"
                            icon={<Icons.ClockIcon className="h-5 w-5" />}
                        />
                        <StatCard
                            label={t('shell.demo.cards.revenue')}
                            value="ETB 412,500"
                            tone="success"
                            icon={<Icons.ReceiptTextIcon className="h-5 w-5" />}
                        />
                        <StatCard
                            label={t('shell.demo.cards.rejected')}
                            value={9}
                            tone="neutral"
                            icon={<Icons.XCircle className="h-5 w-5" />}
                        />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <div>
                                    <CardTitle>
                                        {t('shell.demo.cards.title')}
                                    </CardTitle>
                                    <CardDescription>
                                        {t('shell.demo.cards.description')}
                                    </CardDescription>
                                </div>
                                <StatusBadge status="active" />
                            </CardHeader>
                            <p className="text-sm">
                                {t('shell.demo.cards.body')}
                            </p>
                            <CardFooter>
                                <Button variant="outline">
                                    {t('shell.common.cancel')}
                                </Button>
                                <Button>{t('shell.actions.save')}</Button>
                            </CardFooter>
                        </Card>
                        <div className="space-y-3">
                            {(
                                [
                                    'info',
                                    'success',
                                    'warning',
                                    'danger',
                                ] as const
                            ).map((tone) => (
                                <div
                                    key={tone}
                                    className={`card-tone-${tone} rounded-card border border-(--app-border) bg-(--app-surface) p-4 text-sm`}
                                >
                                    <span className="font-medium">
                                        {t(`shell.demo.tones.${tone}`)}
                                    </span>{' '}
                                    — {t('shell.demo.cards.tone_card')}
                                </div>
                            ))}
                        </div>
                    </div>
                </Section>

                <Section id="form" title={t('shell.demo.sections.form')}>
                    <form onSubmit={submit} noValidate className="space-y-6">
                        <FormSection
                            title={t('shell.demo.form.section_title')}
                            description={t(
                                'shell.demo.form.section_description',
                            )}
                            step={1}
                        >
                            <FormField
                                label={t('shell.demo.form.name')}
                                required
                                error={form.errors.name}
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Input
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                label={t('shell.demo.form.email')}
                                required
                                error={form.errors.email}
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Input
                                        id={id}
                                        type="email"
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                label={t('shell.demo.form.phone')}
                                description={t('shell.demo.form.phone_hint')}
                                error={form.errors.phone}
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Input
                                        id={id}
                                        type="tel"
                                        inputMode="tel"
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                label={t('shell.demo.form.type')}
                                required
                                error={form.errors.type}
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Select
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.type}
                                        onChange={(event) =>
                                            form.setData(
                                                'type',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('shell.demo.form.choose_type')}
                                        </option>
                                        {types.map((type) => (
                                            <option key={type} value={type}>
                                                {type}
                                            </option>
                                        ))}
                                    </Select>
                                )}
                            </FormField>
                            <FormField
                                label={t('shell.demo.form.axles')}
                                required
                                error={form.errors.axles}
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Input
                                        id={id}
                                        type="number"
                                        min={2}
                                        max={12}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.axles}
                                        onChange={(event) =>
                                            form.setData(
                                                'axles',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField label={t('shell.demo.form.owner')}>
                                {({ id }) => (
                                    <SearchableSelect
                                        id={id}
                                        value={officer}
                                        onChange={setOfficer}
                                        placeholder={t(
                                            'shell.demo.form.owner_placeholder',
                                        )}
                                        options={[
                                            'Abebe Kebede',
                                            'Tigist Haile',
                                            'Dawit Alemu',
                                            'Selam Tesfaye',
                                            'Mekdes Girma',
                                        ].map((name) => ({
                                            value: name,
                                            label: name,
                                        }))}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label={t('shell.demo.form.notes')}
                                description={t('shell.demo.form.notes_hint')}
                                error={form.errors.notes}
                                className="sm:col-span-2"
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Textarea
                                        id={id}
                                        rows={3}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <div className="sm:col-span-2">
                                <FileUpload
                                    label={t('shell.demo.form.document')}
                                    accept=".pdf,.png,.jpg"
                                    maxSizeMb={2}
                                    value={form.data.document}
                                    error={form.errors.document}
                                    onChange={(file) =>
                                        form.setData('document', file)
                                    }
                                    onRemove={() =>
                                        form.setData('document', null)
                                    }
                                />
                            </div>
                            <FormField
                                label={t('shell.demo.form.search')}
                                className="sm:col-span-2"
                            >
                                {() => (
                                    <SearchInput
                                        value={quickSearch}
                                        onChange={setQuickSearch}
                                        onClear={() => setQuickSearch('')}
                                    />
                                )}
                            </FormField>
                        </FormSection>
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => form.reset()}
                            >
                                {t('shell.common.reset')}
                            </Button>
                            <Button type="submit" loading={form.processing}>
                                {t('shell.demo.form.submit')}
                            </Button>
                        </div>
                    </form>
                </Section>

                <Section
                    id="rich-text"
                    title={t('shell.demo.sections.rich_text')}
                >
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <RichTextEditor
                                label={t('shell.demo.rich_text.editor')}
                                value={notice}
                                onChange={setNotice}
                                preset="standard"
                                maxLength={2000}
                            />
                        </Card>
                        <Card>
                            <p className="mb-3 text-xs font-medium text-(--app-muted-foreground)">
                                {t('shell.demo.rich_text.viewer')}
                            </p>
                            <RichTextViewer html={notice} />
                        </Card>
                    </div>
                </Section>

                <Section id="table" title={t('shell.demo.sections.table')}>
                    <AppFilterBar
                        href={PAGE}
                        filters={filters}
                        label={t('shell.demo.sections.table')}
                    >
                        <FilterField label={t('shell.demo.table.search')} wide>
                            <Input
                                type="search"
                                name="search"
                                defaultValue={filters.search}
                            />
                        </FilterField>
                        <FilterField label={t('shell.demo.table.status')}>
                            <Select name="status" defaultValue={filters.status}>
                                <option value="">
                                    {t('shell.demo.table.all_statuses')}
                                </option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {t(`shell.status.${status}`)}
                                    </option>
                                ))}
                            </Select>
                        </FilterField>
                    </AppFilterBar>
                    <Row>
                        <Button
                            variant="outline"
                            size="sm"
                            aria-pressed={tableLoading}
                            onClick={() => setTableLoading((on) => !on)}
                        >
                            {t('shell.demo.table.show_loading')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            aria-pressed={tableEmpty}
                            onClick={() => setTableEmpty((on) => !on)}
                        >
                            {t('shell.demo.table.show_empty')}
                        </Button>
                    </Row>
                    <AppDataTable
                        id="ui-kit-permits"
                        data={tableEmpty ? [] : rows}
                        columns={columns}
                        meta={tableEmpty ? undefined : meta}
                        filters={filters}
                        href={PAGE}
                        loading={tableLoading}
                        emptyTitle={t('shell.demo.table.empty')}
                    />
                </Section>

                <Section
                    id="overlays"
                    title={t('shell.demo.sections.overlays')}
                >
                    <Card>
                        <Row>
                            <Button
                                variant="outline"
                                onClick={() => setModalOpen(true)}
                            >
                                {t('shell.demo.overlays.open_modal')}
                            </Button>
                            <Button
                                variant="destructiveOutline"
                                onClick={() => void ask()}
                            >
                                {t('shell.demo.overlays.confirm')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => void ask(true)}
                            >
                                {t('shell.demo.overlays.confirm_reason')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setDialogOpen(true)}
                            >
                                {t('shell.demo.overlays.dialog')}
                            </Button>
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <Button
                                        variant="secondary"
                                        icon={<Icons.ChevronDown />}
                                        iconPosition="right"
                                    >
                                        {t('shell.demo.overlays.dropdown')}
                                    </Button>
                                </Dropdown.Trigger>
                                <Dropdown.Content align="left">
                                    <Dropdown.Link href="/settings/profile">
                                        {t(
                                            'shell.demo.overlays.dropdown_profile',
                                        )}
                                    </Dropdown.Link>
                                    <Dropdown.Link href={PAGE}>
                                        {t(
                                            'shell.demo.overlays.dropdown_settings',
                                        )}
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </Row>
                    </Card>
                    <Modal
                        show={modalOpen}
                        onClose={() => setModalOpen(false)}
                        maxWidth="md"
                    >
                        <div className="space-y-4 p-6">
                            <h3 className="text-base font-semibold">
                                {t('shell.demo.overlays.modal_title')}
                            </h3>
                            <p className="text-sm text-(--app-muted-foreground)">
                                {t('shell.demo.overlays.modal_body')}
                            </p>
                            <Input
                                aria-label={t('shell.demo.form.name')}
                                placeholder={t('shell.demo.form.name')}
                            />
                            <div className="flex justify-end">
                                <Button onClick={() => setModalOpen(false)}>
                                    {t('shell.actions.close')}
                                </Button>
                            </div>
                        </div>
                    </Modal>
                    <AppDialog
                        open={dialogOpen}
                        onClose={() => setDialogOpen(false)}
                        title={t('shell.demo.overlays.dialog_title')}
                        description={t('shell.demo.overlays.modal_body')}
                        footer={
                            <Button onClick={() => setDialogOpen(false)}>
                                {t('shell.actions.close')}
                            </Button>
                        }
                    />
                </Section>

                <Section
                    id="feedback"
                    title={t('shell.demo.sections.feedback')}
                >
                    <div className="grid gap-3 lg:grid-cols-2">
                        {(
                            ['info', 'success', 'warning', 'danger'] as const
                        ).map((tone) => (
                            <Alert
                                key={tone}
                                tone={tone}
                                title={t(`shell.demo.tones.${tone}`)}
                            >
                                {t(`shell.demo.feedback.${tone}`)}
                            </Alert>
                        ))}
                    </div>
                    <ErrorState
                        title={t('shell.demo.feedback.error_title')}
                        description={t('shell.demo.feedback.error_body')}
                        action={
                            <Button variant="outline" size="sm">
                                {t('shell.actions.retry')}
                            </Button>
                        }
                    />
                    <Card>
                        <EmptyState
                            title={t('shell.demo.feedback.empty_title')}
                            description={t('shell.demo.feedback.empty_body')}
                            action={
                                <Button icon={<Icons.Plus />}>
                                    {t('shell.actions.add')}
                                </Button>
                            }
                        />
                    </Card>
                    <Row>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.success(
                                    t('shell.demo.feedback.toast_text'),
                                )
                            }
                        >
                            {t('shell.demo.feedback.toast_success')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.error(t('shell.demo.feedback.toast_text'))
                            }
                        >
                            {t('shell.demo.feedback.toast_error')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.warning(
                                    t('shell.demo.feedback.toast_text'),
                                )
                            }
                        >
                            {t('shell.demo.feedback.toast_warning')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.info(t('shell.demo.feedback.toast_text'))
                            }
                        >
                            {t('shell.demo.feedback.toast_info')}
                        </Button>
                    </Row>
                </Section>

                <Section id="loading" title={t('shell.demo.sections.loading')}>
                    <Card className="space-y-4">
                        <AppPageHeaderSkeleton />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <AppMetricCardSkeleton />
                            <AppMetricCard label="" value="" loading />
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-3/4" />
                                <AppSkeleton className="h-4 w-1/2" />
                                <AppSkeleton className="h-4 w-2/3" />
                            </div>
                        </div>
                        <div className="overflow-hidden rounded-card border border-(--app-border)">
                            <AppPageLoader label={t('shell.common.loading')} />
                        </div>
                    </Card>
                </Section>

                <Section id="icons" title={t('shell.demo.sections.icons')}>
                    {iconGroups.map((group) => (
                        <Card key={group.title} className="space-y-3">
                            <p className="text-xs font-medium text-(--app-muted-foreground)">
                                {group.title}
                            </p>
                            <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                                {group.icons.map(([name, Icon]) => (
                                    <li
                                        key={name}
                                        className="flex min-w-0 items-center gap-2 rounded-control px-2 py-1.5 text-xs text-(--app-muted-foreground) hover:bg-(--app-surface-muted)"
                                    >
                                        <Icon className="h-4 w-4 shrink-0 text-(--app-foreground)" />
                                        <span className="truncate font-mono">
                                            {name}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    ))}
                </Section>
            </div>
        </AuthenticatedLayout>
    );
}
