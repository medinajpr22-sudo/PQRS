import { cn } from '@/lib/utils';

/** Ícono de la plataforma (panel del super administrador). */
export const PLATFORM_ICON = '/images/brand-icon.png';

/**
 * Bloque de marca de los paneles: un recuadro oscuro con el logo o el
 * nombre en grande y una etiqueta pequeña debajo (como «PQRS»).
 *
 * Con iconUrl, el ícono va a la izquierda sobre un recuadro blanco (los
 * colores del ícono no se leen bien sobre el fondo oscuro del panel).
 */
export function PanelBrand({
    name,
    tag,
    logoUrl,
    iconUrl,
    className,
}: {
    name: string;
    tag: string;
    logoUrl?: string | null;
    iconUrl?: string;
    className?: string;
}) {
    if (iconUrl) {
        return (
            <div
                className={cn(
                    'flex items-center gap-3 rounded-2xl bg-black/25 p-3 text-(--panel-fg)',
                    className,
                )}
            >
                <PanelIcon src={iconUrl} className="size-11" />
                <div className="min-w-0">
                    <p className="truncate text-lg leading-tight font-bold">
                        {name}
                    </p>
                    <p className="mt-1 truncate text-[10px] font-bold tracking-[0.25em] text-[color-mix(in_oklab,var(--panel-accent)_65%,white)] uppercase">
                        {tag}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'rounded-2xl bg-black/25 px-4 py-3 text-(--panel-fg)',
                className,
            )}
        >
            {logoUrl ? (
                <span className="flex h-11 items-center justify-center rounded-xl bg-white px-3">
                    <img
                        src={logoUrl}
                        alt={name}
                        className="max-h-8 w-auto max-w-full object-contain"
                    />
                </span>
            ) : (
                <p
                    className={cn(
                        'line-clamp-2 leading-tight font-bold',
                        name.length > 18 ? 'text-lg' : 'text-xl',
                    )}
                >
                    {name}
                </p>
            )}
            <p className="mt-1 text-right text-[10px] font-bold tracking-[0.25em] text-[color-mix(in_oklab,var(--panel-accent)_65%,white)] uppercase">
                {tag}
            </p>
        </div>
    );
}

/**
 * El ícono sobre un recuadro blanco redondeado.
 */
export function PanelIcon({
    src,
    className,
}: {
    src: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm',
                className,
            )}
        >
            <img src={src} alt="" className="size-full object-contain" />
        </span>
    );
}
