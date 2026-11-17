import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PortalLayout from '@/layouts/portal-layout';
import SettingsLayout from '@/layouts/settings/layout';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import TenantAuthLayout from '@/layouts/tenant-auth-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => {
        const site = document.documentElement.dataset.siteName ?? appName;

        return title ? `${title} · ${site}` : site;
    },
    layout: (name) => {
        switch (true) {
            case name.startsWith('tenant/portal/'):
                return PortalLayout;
            case name === 'tenant/admin/login':
                return TenantAuthLayout;
            case name.startsWith('tenant/admin/'):
                return TenantAdminLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
