import { PanelAuthShell } from '@/components/panel/panel-auth-shell';
import { PanelBrand } from '@/components/panel/panel-brand';
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
