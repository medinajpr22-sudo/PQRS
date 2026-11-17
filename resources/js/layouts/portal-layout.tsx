import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PortalShell } from '@/components/portal/portal-shell';

type Props = {
    children: ReactNode;
    title?: string;
    description?: string;
    active?: 'home' | 'create' | 'track';
};

/**
 * Layout del portal público. El diseño (corporativo, moderno o minimalista),
 * colores, tipografía y logo vienen de la configuración de cada empresa.
 */
export default function PortalLayout({
    children,
    title,
    description,
    active,
}: Props) {
    const { tenant } = usePage().props;

    if (!tenant) {
        return <>{children}</>;
    }

    return (
        <PortalShell
            tenantName={tenant.name}
            theme={tenant.theme}
            title={title}
            description={description}
            active={active}
        >
            {children}
        </PortalShell>
    );
}
