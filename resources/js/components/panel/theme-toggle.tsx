import { Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const MODES: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Modo claro', icon: Sun },
    { value: 'dark', label: 'Modo oscuro', icon: Moon },
];

/**
 * Botones de modo claro y oscuro de la barra superior del panel. La
 * elección se guarda en el navegador de cada persona.
 */
export function ThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="group"
            aria-label="Modo de color"
            className="flex items-center gap-0.5 rounded-full border bg-background p-0.5"
        >
            {MODES.map(({ value, label, icon: Icon }) => {
                const active = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        onClick={() => updateAppearance(value)}
                        aria-pressed={active}
                        aria-label={label}
                        title={label}
                        className={cn(
                            'flex size-8 items-center justify-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            active
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" />
                    </button>
                );
            })}
        </div>
    );
}
