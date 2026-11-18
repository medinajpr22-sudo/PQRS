import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PanelColors } from '@/components/panel/panel-colors';
import { TenantAdminSidebar } from '@/components/tenant-admin-sidebar';
import type { BreadcrumbItem } from '@/types';

/**
 * Panel de gestión de PQRS de cada empresa (administrador y agentes), con
 * los colores de panel que el super administrador le asignó a la empresa.
 */
export default function TenantAdminLayout({
    children,
    breadcrumbs = [],
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { tenant } = usePage().props;

    return (
        <AppShell variant="sidebar">
            <PanelColors
                color={tenant?.theme.panel_color}
                accent={tenant?.theme.panel_accent}
            />
            <TenantAdminSidebar />
            <AppContent
                variant="sidebar"
                className="min-w-0 overflow-x-clip bg-transparent"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
