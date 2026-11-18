import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronsUpDown,
    ExternalLink,
    Inbox,
    LayoutGrid,
    LogOut,
    UserCheck,
    Users,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { TenantAvatar } from '@/components/tenants/tenant-avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useIsMobile } from '@/hooks/use-mobile';
import admin from '@/routes/tenant/admin';
import type { NavItem } from '@/types';

export function TenantAdminSidebar() {
    const page = usePage();
    const { tenant, auth } = page.props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const { currentUrl } = useCurrentUrl();
    const isMine = page.url.includes('assigned=me');

    const items: NavItem[] = [
        {
            title: 'Resumen',
            href: admin.dashboard(),
            icon: LayoutGrid,
            isActive: currentUrl === '/admin',
        },
        {
            title: 'Mis PQRS',
            href: admin.pqrs.index({ query: { assigned: 'me' } }),
            icon: UserCheck,
            isActive: currentUrl === '/admin/pqrs' && isMine,
        },
        {
            title: 'Solicitudes PQRS',
            href: admin.pqrs.index(),
            icon: Inbox,
            isActive: currentUrl.startsWith('/admin/pqrs') && !isMine,
        },
    ];

    // Solo los administradores de la empresa gestionan funcionarios.
    if (auth.user?.role === 'admin') {
        items.push({
            title: 'Usuarios',
            href: admin.users.index(),
            icon: Users,
            isActive: currentUrl.startsWith('/admin/usuarios'),
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={admin.dashboard()} prefetch>
                                {tenant && (
                                    <TenantAvatar
                                        name={tenant.name}
                                        color={tenant.theme.primary_color}
                                        logoUrl={tenant.theme.logo_url}
                                        className="size-8"
                                    />
                                )}
                                <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        {tenant?.name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Gestión de PQRS
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} label="Gestión" />
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Portal</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Ver portal público' }}
                            >
                                <a href="/" target="_blank" rel="noreferrer">
                                    <ExternalLink />
                                    <span>Ver portal público</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                {auth.user && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton
                                        size="lg"
                                        className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                                    >
                                        <UserInfo user={auth.user} />
                                        <ChevronsUpDown className="ml-auto size-4" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                                    align="end"
                                    side={
                                        isMobile
                                            ? 'bottom'
                                            : state === 'collapsed'
                                              ? 'left'
                                              : 'bottom'
                                    }
                                >
                                    <DropdownMenuLabel className="p-0 font-normal">
                                        <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                            <UserInfo
                                                user={auth.user}
                                                showEmail
                                            />
                                        </div>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link
                                            className="block w-full cursor-pointer"
                                            href={admin.logout()}
                                            as="button"
                                            onClick={() => router.flushAll()}
                                        >
                                            <LogOut className="mr-2" />
                                            Cerrar sesión
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarFooter>
        </Sidebar>
    );
}
