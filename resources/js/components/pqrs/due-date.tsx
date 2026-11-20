import { daysUntil, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PqrsStatus } from '@/types';

/**
 * Días antes del vencimiento en que una solicitud pasa a «por vencer».
 * El mismo margen que usa el tablero (DashboardController → due_soon).
 */
export const DUE_SOON_DAYS = 3;

type Light = 'on_time' | 'due_soon' | 'overdue' | 'done' | 'no_due';

const LIGHTS: Record<Light, { label: string; dot: string }> = {
    on_time: { label: 'A tiempo', dot: 'bg-emerald-500' },
    due_soon: { label: 'Por vencer', dot: 'bg-amber-400' },
    overdue: { label: 'Vencida', dot: 'bg-red-600' },
    done: {
        label: 'Atendida',
        dot: 'border-2 border-zinc-300 bg-transparent dark:border-zinc-600',
    },
    no_due: { label: 'Sin plazo', dot: 'bg-zinc-300 dark:bg-zinc-600' },
};

function isOpen(status: PqrsStatus): boolean {
    return status === 'radicada' || status === 'en_tramite';
}

/**
 * Color del semáforo de una solicitud. «Vencida» la decide el servidor
 * (is_overdue), que es quien conoce la hora exacta del plazo.
 */
export function dueLight(pqrs: {
    status: PqrsStatus;
    due_at: string | null;
    is_overdue: boolean;
}): Light {
    if (!isOpen(pqrs.status)) {
        return 'done';
    }

    if (pqrs.is_overdue) {
        return 'overdue';
    }

    const days = daysUntil(pqrs.due_at);

    if (days === null) {
        return 'no_due';
    }

    return days <= DUE_SOON_DAYS ? 'due_soon' : 'on_time';
}

/**
 * Punto del semáforo: verde a tiempo, amarillo por vencer, rojo vencida y
 * gris (hueco) si ya se atendió. El texto va para lectores de pantalla y al
 * pasar el mouse; la fecha de la columna «Vence» dice lo mismo en palabras.
 */
export function DueLight({
    pqrs,
    className,
}: {
    pqrs: { status: PqrsStatus; due_at: string | null; is_overdue: boolean };
    className?: string;
}) {
    const light = LIGHTS[dueLight(pqrs)];

    return (
        <span
            role="img"
            aria-label={`Semáforo: ${light.label}`}
            title={light.label}
            className={cn(
                'inline-block size-3 shrink-0 rounded-full',
                light.dot,
                className,
            )}
        />
    );
}

/**
 * Leyenda del semáforo, encima de la lista.
 */
export function DueLightLegend({ className }: { className?: string }) {
    const items: { light: Light; label: string }[] = [
        { light: 'on_time', label: 'A tiempo' },
        {
            light: 'due_soon',
            label: `Por vencer (${DUE_SOON_DAYS} días o menos)`,
        },
        { light: 'overdue', label: 'Vencida' },
        { light: 'done', label: 'Atendida' },
    ];

    return (
        <ul
            aria-label="Semáforo de vencimiento"
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground',
                className,
            )}
        >
            {items.map(({ light, label }) => (
                <li key={light} className="flex items-center gap-1.5">
                    <span
                        aria-hidden
                        className={cn(
                            'inline-block size-2.5 rounded-full',
                            LIGHTS[light].dot,
                        )}
                    />
                    {label}
                </li>
            ))}
        </ul>
    );
}

/**
 * Fecha de vencimiento con indicador de días restantes.
 */
export function DueDate({
    dueAt,
    open,
    overdue = false,
    className,
}: {
    dueAt: string | null;
    open: boolean;
    /** Lo que dice el servidor: vencida aunque falten horas para cambiar de día. */
    overdue?: boolean;
    className?: string;
}) {
    const days = daysUntil(dueAt);
    const late = overdue || (days !== null && days < 0);

    return (
        <div className={cn('leading-tight', className)}>
            <p className="tabular-nums">{formatDate(dueAt)}</p>
            {open && days !== null && (
                <p
                    className={cn(
                        'text-xs',
                        late
                            ? 'font-medium text-red-600 dark:text-red-400'
                            : days <= DUE_SOON_DAYS
                              ? 'font-medium text-amber-600 dark:text-amber-400'
                              : 'text-muted-foreground',
                    )}
                >
                    {late
                        ? days < 0
                            ? `Vencida hace ${Math.abs(days)} día${Math.abs(days) === 1 ? '' : 's'}`
                            : 'Venció hoy'
                        : days === 0
                          ? 'Vence hoy'
                          : `Faltan ${days} día${days === 1 ? '' : 's'}`}
                </p>
            )}
        </div>
    );
}
