import type { ReactNode } from 'react';

/**
 * Pantalla de inicio de sesión de los paneles: fondo del color del panel
 * con formas decorativas y una tarjeta blanca centrada con la marca.
 */
export function PanelAuthShell({
    brand,
    title,
    description,
    children,
}: {
    brand: ReactNode;
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="relative flex min-h-svh items-center justify-center overflow-hidden bg-(--panel) p-4 sm:p-8">
            <div
                aria-hidden
                className="absolute inset-0"
                style={{
                    backgroundImage:
                        'radial-gradient(60rem 40rem at 100% 0%, color-mix(in oklab, var(--panel-accent) 45%, transparent), transparent 60%), radial-gradient(50rem 40rem at 0% 100%, color-mix(in oklab, var(--panel) 60%, black), transparent 70%)',
                }}
            />
            <div
                aria-hidden
                className="absolute top-[12%] left-[8%] hidden size-72 rounded-full border-[44px] border-(--panel-accent) opacity-80 shadow-2xl md:block"
            />
            <div
                aria-hidden
                className="absolute bottom-[10%] left-[18%] hidden h-56 w-24 -rotate-[35deg] rounded-[2rem] bg-white/80 shadow-2xl md:block"
            />

            <div className="relative w-full max-w-md rounded-3xl bg-white p-8 text-zinc-900 shadow-2xl sm:p-9">
                {brand}
                {title && (
                    <div className="mt-6 mb-6">
                        <h1 className="text-3xl font-bold tracking-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-1 text-zinc-500">{description}</p>
                        )}
                    </div>
                )}
                {children}
            </div>
        </div>
    );
}
