import { cn } from '@/lib/utils';

/**
 * Bloque de marca de los paneles: un recuadro oscuro con el logo o el
 * nombre en grande y una etiqueta pequeña debajo (como «PQRS»).
 */
export function PanelBrand({
    name,
    tag,
    logoUrl,
    className,
}: {
    name: string;
    tag: string;
    logoUrl?: string | null;
    className?: string;
}) {
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
