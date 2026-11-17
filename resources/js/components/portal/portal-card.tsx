import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function PortalCard({
    title,
    description,
    step,
    children,
    className,
}: {
    title?: string;
    description?: ReactNode;
    step?: number;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-7',
                className,
            )}
        >
            {title && (
                <header className="mb-5 flex items-start gap-3">
                    {step !== undefined && (
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-(--brand) text-xs font-bold text-(--brand-fg)">
                            {step}
                        </span>
                    )}
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            {title}
                        </h2>
                        {description && (
                            <p className="mt-0.5 text-sm text-zinc-500">
                                {description}
                            </p>
                        )}
                    </div>
                </header>
            )}
            {children}
        </section>
    );
}
