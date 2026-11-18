import { Link } from '@inertiajs/react';
import { Building2, LayoutGrid, PlusCircle } from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { PanelBrand } from '@/components/panel/panel-brand';
import { PanelSidebarUser } from '@/components/panel/panel-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import tenants from '@/routes/tenants';
import type { NavItem } from '@/types';

const generalItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const companyItems: NavItem[] = [
    {
        title: 'Empresas',
        href: tenants.index(),
        icon: Building2,
    },
    {
        title: 'Nueva empresa',
        href: tenants.create(),
        icon: PlusCircle,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="floating">
            <SidebarHeader className="p-3">
                <Link href={dashboard()} prefetch>
                    <PanelBrand
                        name="Portal PQRS"
                        tag="Administración"
                        className="group-data-[collapsible=icon]:hidden"
                    />
                    <span className="hidden size-8 items-center justify-center rounded-lg bg-black/25 text-sm font-bold text-(--panel-fg) group-data-[collapsible=icon]:flex">
                        P
                    </span>
                </Link>
            </SidebarHeader>

            <SidebarContent className="gap-2 pt-2">
                <NavMain items={generalItems} label="General" />
                <NavMain items={companyItems} label="Empresas" />
            </SidebarContent>

            <SidebarFooter className="p-3">
                <PanelSidebarUser />
            </SidebarFooter>
        </Sidebar>
    );
}
