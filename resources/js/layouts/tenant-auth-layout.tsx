import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PanelAuthShell } from '@/components/panel/panel-auth-shell';
import { PanelBrand } from '@/components/panel/panel-brand';
import { PanelColors } from '@/components/panel/panel-colors';

/**
 * Inicio de sesión del personal de una empresa, con su logo y los colores
 * de su panel administrativo.
 */
export default function TenantAuthLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { tenant } = usePage().props;

    if (!tenant) {
        return <>{children}</>;
    }

    return (
        <>
            <PanelColors
                color={tenant.theme.panel_color}
                accent={tenant.theme.panel_accent}
            />
            <PanelAuthShell
                brand={
                    <PanelBrand
                        name={tenant.name}
                        tag="PQRS"
                        logoUrl={tenant.theme.logo_url}
                        className="bg-(--panel)"
                    />
                }
            >
                {children}
            </PanelAuthShell>
        </>
    );
}
