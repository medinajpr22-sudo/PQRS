import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PqrsStatus } from '@/types';

const STEPS: { key: PqrsStatus; label: string }[] = [
    { key: 'radicada', label: 'Radicada' },
    { key: 'en_tramite', label: 'En trámite' },
    { key: 'respondida', label: 'Respondida' },
    { key: 'cerrada', label: 'Cerrada' },
];

/**
 * Línea de progreso del estado de una PQRS (usa el color de marca --brand).
 */
export function StatusSteps({ status }: { status: PqrsStatus }) {
    const current = STEPS.findIndex((s) => s.key === status);

    return (
        <ol className="grid grid-cols-4">
            {STEPS.map((step, i) => {
                const done = i <= current;

                return (
                    <li
                        key={step.key}
                        className="relative flex flex-col items-center gap-2 text-center"
                    >
                        {i > 0 && (
                            <span
                                aria-hidden
                                className={cn(
                                    'absolute top-4 right-1/2 h-0.5 w-full -translate-y-1/2',
                                    done ? 'bg-(--brand)' : 'bg-zinc-200',
                                )}
                            />
                        )}
                        <span
                            className={cn(
                                'relative z-10 flex size-8 items-center justify-center rounded-full border-2 text-xs font-semibold',
                                done
                                    ? 'border-(--brand) bg-(--brand) text-(--brand-fg)'
                                    : 'border-zinc-200 bg-white text-zinc-400',
                                i === current && 'ring-4 ring-(--brand-soft)',
                            )}
                        >
                            {done ? <Check className="size-4" /> : i + 1}
                        </span>
                        <span
                            className={cn(
                                'text-xs',
                                done
                                    ? 'font-medium text-zinc-900'
                                    : 'text-zinc-400',
                            )}
                        >
                            {step.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
