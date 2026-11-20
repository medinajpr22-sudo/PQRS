import { Head, Link, router } from '@inertiajs/react';
import { Download, Inbox, Search, X } from 'lucide-react';
import { useState } from 'react';
import { DueDate } from '@/components/pqrs/due-date';
import { FilterPills } from '@/components/pqrs/filter-pills';
import { PageHeader } from '@/components/pqrs/page-header';
import { Pagination } from '@/components/pqrs/pagination';
import {
    OverdueBadge,
    RiskBadge,
    StatusBadge,
} from '@/components/pqrs/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate } from '@/lib/format';
import { filtersToQuery, hasActiveFilters } from '@/lib/pqrs-filters';
import admin from '@/routes/tenant/admin';
import type {
    Option,
    Paginated,
    PqrsFilterValues,
    PqrsListItem,
    StaffOption,
} from '@/types';

type Props = {
    pqrs: Paginated<PqrsListItem>;
    filters: PqrsFilterValues;
    statuses: Option[];
    types: Option[];
    staff: StaffOption[];
    sedes: Option[];
    counts: { all: number; mine: number; none: number };
    canExport: boolean;
};

export default function PqrsIndex({
    pqrs,
    filters,
    statuses,
    types,
    staff,
    sedes,
    counts,
    canExport,
}: Props) {
    const isMine = filters.assigned === 'me';
    const [search, setSearch] = useState(filters.search);

    const apply = (next: Partial<PqrsFilterValues>) => {
        router.get(
            admin.pqrs.index.url({
                query: filtersToQuery({ ...filters, search, ...next }),
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const hasFilters = hasActiveFilters(filters);

    return (
        <>
            <Head title={isMine ? 'Mis PQRS' : 'Solicitudes PQRS'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={isMine ? 'Mis PQRS' : 'Solicitudes PQRS'}
                    description={
                        isMine
                            ? 'Solicitudes de las que eres responsable.'
                            : 'Busca, filtra y gestiona las solicitudes radicadas.'
                    }
                    actions={
                        canExport && (
                            <Button
                                asChild
                                className="h-11 rounded-xl px-5 font-bold shadow-md"
                            >
                                {/* Descarga lo que se ve: con los mismos filtros. */}
                                <a
                                    href={admin.pqrs.export.url({
                                        query: filtersToQuery(filters),
                                    })}
                                >
                                    <Download />
                                    Descargar Excel
                                </a>
                            </Button>
                        )
                    }
                />

                <FilterPills
                    label="Responsable"
                    options={[
                        { value: null, label: 'Todas', count: counts.all },
                        { value: 'me', label: 'Mis PQRS', count: counts.mine },
                        {
                            value: 'none',
                            label: 'Sin responsable',
                            count: counts.none,
                        },
                    ]}
                    value={
                        filters.assigned === 'me' || filters.assigned === 'none'
                            ? filters.assigned
                            : null
                    }
                    onChange={(assigned) => apply({ assigned: assigned ?? '' })}
                />

                <Card className="gap-0 py-0">
                    <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center">
                        <form
                            className="relative flex-1"
                            onSubmit={(e) => {
                                e.preventDefault();
                                apply({ search });
                            }}
                        >
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por radicado, nombre, correo, documento o asunto…"
                                className="pl-9"
                            />
                        </form>
                        <div className="flex flex-wrap items-center gap-3">
                            <NativeSelect
                                aria-label="Estado"
                                value={filters.status ?? ''}
                                onChange={(e) =>
                                    apply({ status: e.target.value })
                                }
                                className="w-40"
                            >
                                <option value="">Todos los estados</option>
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <NativeSelect
                                aria-label="Tipo"
                                value={filters.type ?? ''}
                                onChange={(e) =>
                                    apply({ type: e.target.value })
                                }
                                className="w-40"
                            >
                                <option value="">Todos los tipos</option>
                                {types.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            {sedes.length > 0 && (
                                <NativeSelect
                                    aria-label="Sede"
                                    value={filters.sede ?? ''}
                                    onChange={(e) =>
                                        apply({ sede: e.target.value })
                                    }
                                    className="w-44"
                                >
                                    <option value="">
                                        {sedes.length > 1
                                            ? 'Todas mis sedes'
                                            : 'Mi sede'}
                                    </option>
                                    {sedes.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                            <NativeSelect
                                aria-label="Responsable"
                                value={filters.assigned ?? ''}
                                onChange={(e) =>
                                    apply({ assigned: e.target.value })
                                }
                                className="w-44"
                            >
                                <option value="">Todos los responsables</option>
                                <option value="me">Asignadas a mí</option>
                                <option value="none">Sin responsable</option>
                                {staff.map((s) => (
                                    <option
                                        key={s.value}
                                        value={String(s.value)}
                                    >
                                        {s.label}
                                    </option>
                                ))}
                            </NativeSelect>
                            <Input
                                type="date"
                                aria-label="Radicadas desde"
                                title="Radicadas desde"
                                value={filters.from}
                                max={filters.to || undefined}
                                onChange={(e) =>
                                    apply({ from: e.target.value })
                                }
                                className="w-38"
                            />
                            <span className="text-sm text-muted-foreground">
                                a
                            </span>
                            <Input
                                type="date"
                                aria-label="Radicadas hasta"
                                title="Radicadas hasta"
                                value={filters.to}
                                min={filters.from || undefined}
                                onChange={(e) => apply({ to: e.target.value })}
                                className="w-38"
                            />
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="overdue"
                                    checked={filters.overdue}
                                    onCheckedChange={(v) =>
                                        apply({ overdue: v === true })
                                    }
                                />
                                <Label htmlFor="overdue">Solo vencidas</Label>
                            </div>
                            {hasFilters && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearch('');
                                        router.get(admin.pqrs.index.url());
                                    }}
                                >
                                    <X />
                                    Limpiar
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Radicado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Solicitante
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Asunto
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Responsable
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Radicada
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Vence
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {pqrs.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-16 text-center"
                                        >
                                            <Inbox className="mx-auto size-8 text-muted-foreground" />
                                            <p className="mt-2 font-medium">
                                                No hay solicitudes
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Prueba con otros filtros.'
                                                    : 'Las solicitudes radicadas en el portal aparecerán aquí.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                                {pqrs.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="cursor-pointer transition-colors hover:bg-muted/40"
                                        onClick={() =>
                                            router.visit(
                                                admin.pqrs.show.url(p.radicado),
                                            )
                                        }
                                    >
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <Link
                                                href={admin.pqrs.show(
                                                    p.radicado,
                                                )}
                                                className="font-mono text-xs font-semibold hover:underline"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                {p.radicado}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {p.type_label}
                                                {' · '}
                                                {p.sede?.name ?? 'Sin sede'}
                                            </p>
                                        </td>
                                        <td className="max-w-48 px-4 py-3">
                                            <p className="truncate font-medium">
                                                {p.requester_name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {p.requester_email}
                                            </p>
                                        </td>
                                        <td className="max-w-72 px-4 py-3">
                                            <p className="truncate">
                                                {p.subject}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col items-start gap-1">
                                                <StatusBadge
                                                    status={p.status}
                                                    label={p.status_label}
                                                />
                                                {p.is_overdue && (
                                                    <OverdueBadge />
                                                )}
                                                {p.risk_level && (
                                                    <RiskBadge
                                                        level={p.risk_level}
                                                        label={
                                                            p.risk_label ?? ''
                                                        }
                                                    />
                                                )}
                                            </div>
                                        </td>
                                        <td className="max-w-40 px-4 py-3">
                                            <p
                                                className={
                                                    p.assignee
                                                        ? 'truncate'
                                                        : 'truncate text-muted-foreground'
                                                }
                                            >
                                                {p.assignee?.name ??
                                                    'Sin asignar'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                            {formatDate(p.created_at)}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <DueDate
                                                dueAt={p.due_at}
                                                open={
                                                    p.status === 'radicada' ||
                                                    p.status === 'en_tramite'
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <Pagination page={pqrs} noun="solicitudes" />
                </Card>
            </div>
        </>
    );
}

PqrsIndex.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Solicitudes', href: admin.pqrs.index() },
    ],
};
