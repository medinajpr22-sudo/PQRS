import { daysUntil, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Fecha de vencimiento con indicador de días restantes.
 */
export function DueDate({
    dueAt,
    open,
    className,
}: {
    dueAt: string | null;
    open: boolean;
    className?: string;
}) {
    const days = daysUntil(dueAt);

    return (
        <div className={cn('leading-tight', className)}>
            <p className="tabular-nums">{formatDate(dueAt)}</p>
            {open && days !== null && (
                <p
                    className={cn(
                        'text-xs',
                        days < 0
                            ? 'font-medium text-red-600 dark:text-red-400'
                            : days <= 3
                              ? 'font-medium text-amber-600 dark:text-amber-400'
                              : 'text-muted-foreground',
                    )}
                >
                    {days < 0
                        ? `Vencida hace ${Math.abs(days)} día${Math.abs(days) === 1 ? '' : 's'}`
                        : days === 0
                          ? 'Vence hoy'
                          : `Faltan ${days} día${days === 1 ? '' : 's'}`}
                </p>
            )}
        </div>
    );
}
