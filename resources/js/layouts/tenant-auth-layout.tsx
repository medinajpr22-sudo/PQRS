import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { brandStyle } from '@/components/portal/portal-shell';
import { TenantAvatar } from '@/components/tenants/tenant-avatar';

/**
 * Login de funcionarios con la marca de la empresa.
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
        <div
            style={brandStyle(tenant.theme)}
            className="grid min-h-svh bg-background lg:grid-cols-2"
        >
            <div className="relative hidden overflow-hidden bg-(--brand) p-10 text-(--brand-fg) lg:flex lg:flex-col">
                <div
                    aria-hidden
                    className="absolute -right-32 -bottom-32 size-96 rounded-full bg-(--brand-accent) opacity-25 blur-3xl"
                />
                <div className="relative flex items-center gap-3">
                    <span className="rounded-xl bg-white p-1.5">
                        <TenantAvatar
                            name={tenant.name}
                            color={tenant.theme.primary_color}
                            logoUrl={tenant.theme.logo_url}
                            className="border-0"
                        />
                    </span>
                    <span className="font-semibold">{tenant.name}</span>
                </div>
                <div className="relative mt-auto max-w-md">
                    <p className="text-3xl leading-tight font-bold">
                        Gestión de peticiones, quejas, reclamos y sugerencias
                    </p>
                    <p className="mt-3 opacity-85">
                        Atiende las solicitudes de tus usuarios dentro de los
                        plazos de ley y con trazabilidad completa.
                    </p>
                </div>
            </div>
            <div className="flex items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm">{children}</div>
            </div>
        </div>
    );
}
