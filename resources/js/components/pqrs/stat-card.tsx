import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    tone?: 'default' | 'warning' | 'danger' | 'success';
}) {
    const toneClass = {
        default: 'bg-muted text-muted-foreground',
        warning:
            'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        danger: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
        success:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    }[tone];

    return (
        <div className="rounded-xl border bg-card p-5 shadow-xs">
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-muted-foreground">
                    {label}
                </p>
                {Icon && (
                    <span
                        className={cn(
                            'flex size-8 items-center justify-center rounded-lg',
                            toneClass,
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                )}
            </div>
            <p className="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            {hint && (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
