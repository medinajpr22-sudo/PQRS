import {
    AlertTriangle,
    CheckCircle2,
    CircleDot,
    Clock,
    Lock,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PqrsStatus } from '@/types';

const styles: Record<PqrsStatus, { className: string; icon: typeof Clock }> = {
    radicada: {
        className:
            'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
        icon: CircleDot,
    },
    en_tramite: {
        className:
            'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
        icon: Clock,
    },
    respondida: {
        className:
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        icon: CheckCircle2,
    },
    cerrada: {
        className:
            'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
        icon: Lock,
    },
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: PqrsStatus;
    label: string;
    className?: string;
}) {
    const { className: style, icon: Icon } = styles[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                style,
                className,
            )}
        >
            <Icon className="size-3" />
            {label}
        </span>
    );
}

export function OverdueBadge({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
                className,
            )}
        >
            <AlertTriangle className="size-3" />
            Vencida
        </span>
    );
}

/**
 * Nivel de riesgo de un reclamo en salud. El riesgo vital se resalta.
 */
export function RiskBadge({
    level,
    label,
    className,
}: {
    level: 'simple' | 'priorizado' | 'vital';
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                level === 'vital' &&
                    'border-red-600 bg-red-600 text-white dark:border-red-500 dark:bg-red-600',
                level === 'priorizado' &&
                    'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300',
                level === 'simple' &&
                    'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
                className,
            )}
        >
            {label}
        </span>
    );
}

export function TenantStatusBadge({
    status,
}: {
    status: 'active' | 'suspended';
}) {
    return status === 'active' ? (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            <span className="size-1.5 rounded-full bg-emerald-500" />
            Activa
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
            <span className="size-1.5 rounded-full bg-zinc-400" />
            Suspendida
        </span>
    );
}
