import { Link, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    Inbox,
    LayoutGrid,
    LifeBuoy,
    MapPin,
    Palette,
    UserCheck,
    Users,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { PanelBrand } from '@/components/panel/panel-brand';
import { PanelSidebarUser } from '@/components/panel/panel-user';
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
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { initials } from '@/lib/format';
import admin from '@/routes/tenant/admin';
import type { NavItem } from '@/types';

/**
 * Menú del panel de una empresa. Los agentes ven la gestión de sus PQRS;
 * el administrador de empresa además gestiona usuarios y sedes.
 */
export function TenantAdminSidebar() {
    const page = usePage();
    const { tenant, auth } = page.props;
    const { currentUrl } = useCurrentUrl();
    const isMine = page.url.includes('assigned=me');

    const management: NavItem[] = [
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

    // Solo el administrador de empresa gestiona usuarios, sedes y colores.
    const administration: NavItem[] =
        auth.user?.role === 'admin'
            ? [
                  {
                      title: 'Usuarios',
                      href: admin.users.index(),
                      icon: Users,
                      isActive: currentUrl.startsWith('/admin/usuarios'),
                  },
                  {
                      title: 'Sedes',
                      href: admin.sedes.index(),
                      icon: MapPin,
                      isActive: currentUrl.startsWith('/admin/sedes'),
                  },
                  {
                      title: 'Colores',
                      href: admin.branding.edit(),
                      icon: Palette,
                      isActive: currentUrl.startsWith('/admin/colores'),
                  },
              ]
            : [];

    // Mesa de ayuda de TIC: para todos (administrador y agentes).
    const support: NavItem[] = [
        {
            title: 'Mesa de ayuda',
            href: admin.help(),
            icon: LifeBuoy,
            isActive: currentUrl.startsWith('/admin/ayuda'),
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="floating">
            <SidebarHeader className="p-3">
                <Link href={admin.dashboard()} prefetch>
                    <PanelBrand
                        name={tenant?.name ?? ''}
                        tag="PQRS"
                        logoUrl={tenant?.theme.logo_url}
                        className="group-data-[collapsible=icon]:hidden"
                    />
                    <span className="hidden size-8 items-center justify-center rounded-lg bg-black/25 text-xs font-bold text-(--panel-fg) group-data-[collapsible=icon]:flex">
                        {initials(tenant?.name ?? '')}
                    </span>
                </Link>
            </SidebarHeader>

            <SidebarContent className="gap-2 pt-2">
                <NavMain items={management} label="Gestión" />
                {administration.length > 0 && (
                    <NavMain items={administration} label="Administración" />
                )}
                <NavMain items={support} label="Soporte" />
                <SidebarGroup className="px-2 py-1">
                    <SidebarGroupLabel className="text-[11px] font-bold tracking-[0.16em] text-sidebar-foreground/70 uppercase">
                        Portal
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip={{ children: 'Ver portal público' }}
                                className="h-11 rounded-xl px-3 text-[15px] font-semibold [&>svg]:size-5"
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

            <SidebarFooter className="p-3">
                <PanelSidebarUser />
            </SidebarFooter>
        </Sidebar>
    );
}
