import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { create, destroy, edit, index } from '@/routes/admin/branches';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import type { BreadcrumbItem } from '@/types';
import type { AdminBranch } from '@/types/admin';

type Props = {
    branches: (AdminBranch & { staff: number; permits: number })[];
    /** Whether the permit number rule names the branch. */
    in_number: boolean;
    /** What this person may do here (each its own permission). */
    can: { create: boolean; update: boolean; delete: boolean };
};

function BranchesIndexPage({ branches, in_number, can }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.branches.title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.branches.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.branches.title')}
                    action={
                        can.create && (
                            <Button
                                asChild
                                size="lg"
                                className="w-full sm:w-auto"
                            >
                                <Link href={create()}>
                                    <Plus aria-hidden="true" />
                                    {t('admin.branches.new')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <p className="rounded-card border border-border bg-muted p-4 text-sm">
                    {in_number
                        ? t('admin.branches.in_number')
                        : t('admin.branches.not_in_number')}
                </p>

                <TableCard toolsId="branches">
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('admin.branches.fields.code')}</Th>
                                <Th>{t('admin.branches.fields.name_am')}</Th>
                                <Th>{t('admin.branches.fields.name_en')}</Th>
                                <Th align="right">
                                    {t('admin.branches.staff')}
                                </Th>
                                <Th align="right">
                                    {t('admin.branches.permits')}
                                </Th>
                                <Th>{t('common.status')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {branches.length === 0 ? (
                                <EmptyRow colSpan={7}>
                                    {t('admin.branches.empty')}
                                </EmptyRow>
                            ) : (
                                branches.map((branch) => (
                                    <Tr key={branch.id}>
                                        <Td>
                                            <code
                                                translate="no"
                                                className="rounded-xs bg-muted px-1.5 py-0.5 text-xs"
                                            >
                                                {branch.code}
                                            </code>
                                        </Td>
                                        <Td lang="am" className="font-medium">
                                            {branch.name_am}
                                        </Td>
                                        <Td lang="en">{branch.name_en}</Td>
                                        <Td numeric>{branch.staff}</Td>
                                        <Td numeric>{branch.permits}</Td>
                                        <Td>
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                    branch.is_active
                                                        ? 'bg-success-soft text-success-strong ring-success-strong/20'
                                                        : 'bg-muted text-muted-foreground ring-border',
                                                )}
                                            >
                                                {branch.is_active
                                                    ? t('admin.branches.active')
                                                    : t(
                                                          'admin.branches.inactive',
                                                      )}
                                            </span>
                                        </Td>
                                        <Td align="right">
                                            <RowActions
                                                name={branch.name_am}
                                                editHref={
                                                    can.update
                                                        ? edit.url({
                                                              branch: branch.id,
                                                          })
                                                        : null
                                                }
                                                deleteUrl={
                                                    can.delete
                                                        ? destroy.url({
                                                              branch: branch.id,
                                                          })
                                                        : null
                                                }
                                                deletable={
                                                    branch.staff === 0 &&
                                                    branch.permits === 0
                                                }
                                            />
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>
            </div>
        </AppLayout>
    );
}

export default function BranchesIndex(props: Props) {
    return (
        <ConfirmProvider>
            <BranchesIndexPage {...props} />
        </ConfirmProvider>
    );
}
