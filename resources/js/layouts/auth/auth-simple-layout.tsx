import { PanelAuthShell } from '@/components/panel/panel-auth-shell';
import { PanelBrand, PLATFORM_ICON } from '@/components/panel/panel-brand';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <PanelAuthShell
            brand={
                <PanelBrand
                    name="Portal PQRS"
                    tag="Administración"
                    iconUrl={PLATFORM_ICON}
                    className="bg-(--panel)"
                />
            }
            title={title}
            description={description}
        >
            {children}
        </PanelAuthShell>
    );
}
