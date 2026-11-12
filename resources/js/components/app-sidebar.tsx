import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useNavGroups } from '@/hooks/use-nav-groups';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/**
 * The EUISIS sidebar: a white column with the brand block on top, sections
 * with small uppercase labels, and the current page marked by a brand-colour
 * rule on its start edge and a brand-colour icon. Collapses to an icon rail.
 */
export function AppSidebar() {
    const { t } = useTranslation();
    const groups = useNavGroups();

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border px-3 group-data-[collapsible=icon]:px-2">
                <Link
                    href={dashboard()}
                    prefetch
                    className="flex min-w-0 items-center gap-3 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-sidebar p-1 ring-1 ring-sidebar-border">
                        <BrandMark className="size-full" />
                    </span>
                    <span className="min-w-0 group-data-[collapsible=icon]:hidden">
                        <span className="block truncate text-sm leading-tight font-bold text-sidebar-foreground">
                            {t('common.app_name')}
                        </span>
                        <span className="mt-0.5 block truncate text-eyebrow text-sidebar-muted">
                            {t('common.authority_short')}
                        </span>
                    </span>
                </Link>
            </SidebarHeader>

            <SidebarContent className="gap-0 px-2 py-3">
                {groups.map((group, index) => (
                    <SidebarGroup
                        key={group.label ?? index}
                        className="px-0 py-1"
                    >
                        {group.label && (
                            <SidebarGroupLabel className="h-auto px-2.5 pt-3 pb-1.5 text-eyebrow text-sidebar-muted">
                                {group.label}
                            </SidebarGroupLabel>
                        )}
                        <SidebarMenu className="gap-0.5">
                            {group.items.map((item) => {
                                const active = item.isActive;

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            tooltip={{ children: item.title }}
                                            className={cn(
                                                'relative h-10 gap-3 px-2.5 font-medium text-sidebar-foreground group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:justify-center',
                                                'data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold',
                                            )}
                                        >
                                            <Link href={item.href} prefetch>
                                                {active && (
                                                    <span
                                                        aria-hidden="true"
                                                        className="absolute inset-y-1.5 start-0 w-0.5 rounded-full bg-sidebar-primary"
                                                    />
                                                )}
                                                {item.icon && (
                                                    <item.icon
                                                        className={cn(
                                                            'size-[18px]! shrink-0',
                                                            active &&
                                                                'text-sidebar-primary',
                                                        )}
                                                    />
                                                )}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>
        </Sidebar>
    );
}
