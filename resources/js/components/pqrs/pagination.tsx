import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination<T>({
    page,
    noun = 'registros',
}: {
    page: Paginated<T>;
    noun?: string;
}) {
    if (page.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 text-sm sm:flex-row">
            <p className="text-muted-foreground">
                Mostrando {page.from}–{page.to} de {page.total} {noun}
            </p>
            {page.last_page > 1 && (
                <nav className="flex flex-wrap gap-1">
                    {page.links.map((link, i) => {
                        const label = link.label
                            .replace('&laquo;', '‹')
                            .replace('&raquo;', '›');

                        return link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                preserveScroll
                                className={cn(
                                    'min-w-8 rounded-md border px-2.5 py-1 text-center transition-colors hover:bg-accent',
                                    link.active &&
                                        'border-primary bg-primary text-primary-foreground hover:bg-primary',
                                )}
                            >
                                {label}
                            </Link>
                        ) : (
                            <span
                                key={i}
                                className="min-w-8 rounded-md border px-2.5 py-1 text-center text-muted-foreground opacity-50"
                            >
                                {label}
                            </span>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}
