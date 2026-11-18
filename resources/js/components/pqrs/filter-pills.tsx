import { cn } from '@/lib/utils';

export type PillOption<T> = {
    value: T;
    label: string;
    count?: number;
};

/**
 * Filtros en forma de píldora (p. ej. «Todas 12 · Mis PQRS 3»). La activa
 * toma el color del menú del panel.
 */
export function FilterPills<T extends string | null>({
    options,
    value,
    onChange,
    label,
}: {
    options: PillOption<T>[];
    value: T;
    onChange: (value: T) => void;
    label: string;
}) {
    return (
        <div role="group" aria-label={label} className="flex flex-wrap gap-2">
            {options.map((option) => {
                const active = option.value === value;

                return (
                    <button
                        key={option.label}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'inline-flex h-10 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-colors',
                            active
                                ? 'border-transparent bg-(--panel) text-(--panel-fg) shadow-sm'
                                : 'bg-card hover:bg-muted',
                        )}
                    >
                        {option.label}
                        {option.count !== undefined && (
                            <span
                                className={cn(
                                    'min-w-6 rounded-full px-1.5 text-center text-[11px] leading-5 font-bold tabular-nums',
                                    active
                                        ? 'bg-(--panel-accent) text-(--panel-accent-fg)'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {option.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
