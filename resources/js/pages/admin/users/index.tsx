import { Head, Link, router } from '@inertiajs/react';
import {
    Lock,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    ShieldOff,
} from 'lucide-react';
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
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, edit, index } from '@/routes/admin/users';
import type { BreadcrumbItem, Paginated } from '@/types';
import type { AdminUser } from '@/types/admin';
import { ROLES } from '@/types/enums';

type Props = {
    users: Paginated<AdminUser>;
    filters: { search: string; role: string };
};

export default function UsersIndex({ users, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.users.title'), href: index() },
    ];

    const filter = (next: { search?: string; role?: string }) => {
        const query = { search: filters.search, role: filters.role, ...next };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(query).filter(([, value]) => value !== ''),
            ),
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filter({ search: search.trim() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.users.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.users.title')}
                    description={t('admin.users.intro')}
                    action={
                        <Button asChild size="lg" className="w-full sm:w-auto">
                            <Link href={create()}>
                                <Plus aria-hidden="true" />
                                {t('admin.users.new')}
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-end gap-4">
                    <form
                        role="search"
                        onSubmit={submitSearch}
                        className="flex min-w-64 flex-1 items-end gap-2"
                    >
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="search">
                                {t('admin.users.search')}
                            </Label>
                            <Input
                                id="search"
                                type="search"
                                value={search}
                                placeholder={t(
                                    'admin.users.search_placeholder',
                                )}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            <Search aria-hidden="true" />
                            {t('admin.users.search')}
                        </Button>
                    </form>

                    <div className="grid w-full gap-1.5 sm:w-56">
                        <Label htmlFor="role">
                            {t('admin.users.filter_role')}
                        </Label>
                        <NativeSelect
                            id="role"
                            value={filters.role}
                            onChange={(event) =>
                                filter({ role: event.target.value })
                            }
                        >
                            <option value="">
                                {t('admin.users.any_role')}
                            </option>
                            {ROLES.map((role) => (
                                <option key={role} value={role}>
                                    {t(`roles.${role}.name`)}
                                </option>
                            ))}
                            <option value="none">
                                {t('admin.users.no_role')}
                            </option>
                        </NativeSelect>
                    </div>
                </div>

                <TableCard>
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('admin.users.fields.name')}</Th>
                                <Th>{t('admin.users.fields.role')}</Th>
                                <Th>{t('admin.users.two_factor')}</Th>
                                <Th>{t('admin.users.joined')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {users.data.length === 0 ? (
                                <EmptyRow colSpan={5}>
                                    {t('admin.users.empty')}
                                </EmptyRow>
                            ) : (
                                users.data.map((user) => (
                                    <Tr key={user.id}>
                                        <Td>
                                            <span className="block font-medium">
                                                {user.name}
                                            </span>
                                            <span
                                                translate="no"
                                                className="block text-xs text-muted-foreground"
                                            >
                                                {user.email}
                                            </span>
                                        </Td>
                                        <Td>
                                            <span className="flex flex-wrap gap-1.5">
                                                {user.roles.length === 0 ? (
                                                    <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                                        {t(
                                                            'admin.users.no_role',
                                                        )}
                                                    </span>
                                                ) : (
                                                    user.roles.map((role) => (
                                                        <span
                                                            key={role}
                                                            className="rounded-md bg-primary-light px-2 py-0.5 text-xs font-medium text-accent-foreground"
                                                        >
                                                            {t(
                                                                `roles.${role}.name`,
                                                            )}
                                                        </span>
                                                    ))
                                                )}
                                            </span>
                                        </Td>
                                        <Td muted className="whitespace-nowrap">
                                            <span className="flex items-center gap-1.5">
                                                {user.two_factor ? (
                                                    <ShieldCheck
                                                        aria-hidden="true"
                                                        className="size-4 text-success"
                                                    />
                                                ) : (
                                                    <ShieldOff
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                )}
                                                {user.two_factor
                                                    ? t(
                                                          'admin.users.two_factor_on',
                                                      )
                                                    : t(
                                                          'admin.users.two_factor_off',
                                                      )}
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {user.created_at && (
                                                <DualDate
                                                    date={user.created_at}
                                                />
                                            )}
                                        </Td>
                                        <Td align="right">
                                            {user.can_change_role ? (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={edit({
                                                            user: user.id,
                                                        })}
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                        {t(
                                                            'admin.users.change_role',
                                                        )}
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <Lock
                                                        aria-hidden="true"
                                                        className="size-3.5"
                                                    />
                                                    {user.role_locked_reason}
                                                </span>
                                            )}
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination links={users.links} meta={users.meta} />
            </div>
        </AppLayout>
    );
}
