import type { PqrsFilterValues } from '@/types';

/**
 * Los filtros de la lista de PQRS como parámetros de la URL (solo los que
 * tienen valor). Se usan para filtrar la lista y para que la descarga en
 * Excel traiga exactamente lo que se ve.
 */
export function filtersToQuery(
    filters: Partial<PqrsFilterValues>,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    for (const key of [
        'search',
        'status',
        'type',
        'sede',
        'assigned',
        'from',
        'to',
    ] as const) {
        const value = filters[key];

        if (value) {
            query[key] = value;
        }
    }

    if (filters.overdue) {
        query.overdue = 1;
    }

    return query;
}

/** ¿Hay algún filtro activo? */
export function hasActiveFilters(filters: Partial<PqrsFilterValues>): boolean {
    return Object.keys(filtersToQuery(filters)).length > 0;
}
