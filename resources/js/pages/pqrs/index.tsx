import { Form, Head, router } from '@inertiajs/react';
import {
    Building2,
    ChevronDown,
    Download,
    FileSpreadsheet,
    History,
    Inbox,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import PqrsController from '@/actions/App/Http/Controllers/Central/PqrsController';
import InputError from '@/components/input-error';
import { DueDate } from '@/components/pqrs/due-date';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import { filtersToQuery, hasActiveFilters } from '@/lib/pqrs-filters';
import { dashboard } from '@/routes';
import pqrsRoutes from '@/routes/pqrs';
import type {
    Option,
    Paginated,
    PqrsDeletionItem,
    PqrsFilterValues,
    PqrsListItem,
} from '@/types';

type Props = {
    companies: Option[];
    company: string | null;
    pqrs: Paginated<PqrsListItem> | null;
    total: number;
    types: Option[];
    sedes: Option[];
    filters: PqrsFilterValues;
    statuses: Option[];
    reasons: Option[];
    deletions: PqrsDeletionItem[];
};

export default function CentralPqrsIndex({
    companies,
    company,
    pqrs,
    total,
    types,
    sedes,
    filters,
    statuses,
    reasons,
    deletions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    // La selección es de la página que se ve: al cambiar de empresa o de
    // página empieza vacía.
    const pageKey = `${company}:${pqrs?.current_page}:${pqrs?.total}`;
    const [selection, setSelection] = useState<{
        key: string;
        ids: number[];
    }>({ key: pageKey, ids: [] });
    const selected = selection.key === pageKey ? selection.ids : [];
    const [deleting, setDeleting] = useState<PqrsListItem[] | null>(null);

    const companyName = companies.find((c) => c.value === company)?.label;
    const rows = pqrs?.data ?? [];
    const filtered = hasActiveFilters(filters);

    const visit = (empresa: string | null, next: Partial<PqrsFilterValues>) => {
        router.get(
            pqrsRoutes.index.url({
                query: {
                    ...(empresa ? { empresa } : {}),
                    ...filtersToQuery({ ...filters, search, ...next }),
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const apply = (next: Partial<PqrsFilterValues>) => visit(company, next);

    const exportUrl = (empresa: string) =>
        pqrsRoutes.export.url({
            query: { empresa, ...filtersToQuery(filters) },
        });

    const toggle = (id: number, on: boolean) =>
        setSelection({
            key: pageKey,
            ids: on ? [...selected, id] : selected.filter((x) => x !== id),
        });

    const allOnPage = rows.length > 0 && selected.length === rows.length;

    return (
        <>
            <Head title="PQRS de las empresas" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="PQRS de las empresas"
                    description="Consulta, descarga en Excel y elimina las solicitudes de cada empresa."
                    actions={
                        companies.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button className="h-11 rounded-xl px-5 font-bold shadow-md">
                                        <Download />
                                        Descargar Excel
                                        <ChevronDown />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-72"
                                >
                                    <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                        Con los filtros que tienes ahora
                                    </DropdownMenuLabel>
                                    {company && (
                                        <DropdownMenuItem asChild>
                                            <a href={exportUrl(company)}>
                                                <FileSpreadsheet />
                                                Solo {companyName}
                                            </a>
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <a href={exportUrl('todas')}>
                                            <Building2 />
                                            <span>
                                                Todas las empresas
                                                <span className="block text-xs text-muted-foreground">
                                                    Sin filtro de sede: cada
                                                    empresa tiene las suyas.
                                                </span>
                                            </span>
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )
                    }
                />

                {companies.length === 0 ? (
                    <Card className="items-center gap-2 rounded-2xl py-16 text-center">
                        <Building2 className="size-8 text-muted-foreground" />
                        <p className="font-medium">Aún no hay empresas</p>
                        <p className="text-sm text-muted-foreground">
                            Cuando crees una, aquí verás sus PQRS.
                        </p>
                    </Card>
                ) : (
                    <Card className="gap-0 overflow-hidden rounded-2xl py-0">
                        <div className="grid gap-3 border-b p-4">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                                <NativeSelect
                                    aria-label="Empresa"
                                    value={company ?? ''}
                                    onChange={(e) =>
                                        // Las sedes y los tipos son de cada empresa.
                                        visit(e.target.value, {
                                            sede: '',
                                            type: '',
                                        })
                                    }
                                    className="h-11 font-semibold lg:w-72"
                                >
                                    {companies.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </NativeSelect>
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
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        placeholder="Buscar por radicado, nombre, correo, documento o asunto…"
                                        aria-label="Buscar PQRS"
                                        className="h-11 pl-9"
                                    />
                                </form>
                            </div>
                            <div className="flex flex-wrap items-end gap-3">
                                <FilterSelect
                                    label="Sede"
                                    value={filters.sede}
                                    onChange={(sede) => apply({ sede })}
                                    options={[
                                        { value: 'none', label: 'Sin sede' },
                                        ...sedes,
                                    ]}
                                    all="Todas las sedes"
                                />
                                <FilterSelect
                                    label="Estado"
                                    value={filters.status}
                                    onChange={(status) => apply({ status })}
                                    options={statuses}
                                    all="Todos los estados"
                                />
                                <FilterSelect
                                    label="Tipo"
                                    value={filters.type}
                                    onChange={(type) => apply({ type })}
                                    options={types}
                                    all="Todos los tipos"
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="from"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Radicadas desde
                                    </Label>
                                    <Input
                                        id="from"
                                        type="date"
                                        value={filters.from}
                                        max={filters.to || undefined}
                                        onChange={(e) =>
                                            apply({ from: e.target.value })
                                        }
                                        className="w-40"
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="to"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Hasta
                                    </Label>
                                    <Input
                                        id="to"
                                        type="date"
                                        value={filters.to}
                                        min={filters.from || undefined}
                                        onChange={(e) =>
                                            apply({ to: e.target.value })
                                        }
                                        className="w-40"
                                    />
                                </div>
                                <div className="flex h-9 items-center gap-2">
                                    <Checkbox
                                        id="overdue"
                                        checked={filters.overdue}
                                        onCheckedChange={(v) =>
                                            apply({ overdue: v === true })
                                        }
                                    />
                                    <Label htmlFor="overdue">
                                        Solo vencidas
                                    </Label>
                                </div>
                                {filtered && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-9"
                                        onClick={() => {
                                            setSearch('');
                                            router.get(
                                                pqrsRoutes.index.url({
                                                    query: company
                                                        ? { empresa: company }
                                                        : {},
                                                }),
                                            );
                                        }}
                                    >
                                        <X />
                                        Limpiar filtros
                                    </Button>
                                )}
                            </div>
                        </div>

                        {selected.length > 0 && (
                            <div className="flex flex-wrap items-center gap-3 border-b bg-destructive/5 px-4 py-3 text-sm">
                                <span className="font-semibold">
                                    {selected.length === 1
                                        ? '1 seleccionada'
                                        : `${selected.length} seleccionadas`}
                                </span>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        setDeleting(
                                            rows.filter((p) =>
                                                selected.includes(p.id),
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                    Eliminar
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setSelection({ key: pageKey, ids: [] })
                                    }
                                >
                                    Quitar selección
                                </Button>
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="w-10 px-4 py-3">
                                            <Checkbox
                                                aria-label="Seleccionar todas las de esta página"
                                                checked={allOnPage}
                                                disabled={rows.length === 0}
                                                onCheckedChange={(v) =>
                                                    setSelection({
                                                        key: pageKey,
                                                        ids:
                                                            v === true
                                                                ? rows.map(
                                                                      (p) =>
                                                                          p.id,
                                                                  )
                                                                : [],
                                                    })
                                                }
                                            />
                                        </th>
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
                                            Radicada
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Vence
                                        </th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rows.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-4 py-16 text-center"
                                            >
                                                <Inbox className="mx-auto size-8 text-muted-foreground" />
                                                <p className="mt-2 font-medium">
                                                    No hay solicitudes
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {filtered
                                                        ? 'Prueba con otros filtros.'
                                                        : `${companyName} aún no tiene PQRS radicadas.`}
                                                </p>
                                            </td>
                                        </tr>
                                    )}
                                    {rows.map((p) => {
                                        const isSelected = selected.includes(
                                            p.id,
                                        );

                                        return (
                                            <tr
                                                key={p.id}
                                                className={
                                                    isSelected
                                                        ? 'bg-destructive/5'
                                                        : undefined
                                                }
                                            >
                                                <td className="px-4 py-3">
                                                    <Checkbox
                                                        aria-label={`Seleccionar ${p.radicado}`}
                                                        checked={isSelected}
                                                        onCheckedChange={(v) =>
                                                            toggle(
                                                                p.id,
                                                                v === true,
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <p className="font-mono text-xs font-semibold">
                                                        {p.radicado}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {p.type_label}
                                                        {' · '}
                                                        {p.sede?.name ??
                                                            'Sin sede'}
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
                                                            label={
                                                                p.status_label
                                                            }
                                                        />
                                                        {p.is_overdue && (
                                                            <OverdueBadge />
                                                        )}
                                                        {p.risk_level && (
                                                            <RiskBadge
                                                                level={
                                                                    p.risk_level
                                                                }
                                                                label={
                                                                    p.risk_label ??
                                                                    ''
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {formatDate(p.created_at)}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <DueDate
                                                        dueAt={p.due_at}
                                                        open={
                                                            p.status ===
                                                                'radicada' ||
                                                            p.status ===
                                                                'en_tramite'
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive"
                                                        aria-label={`Eliminar ${p.radicado}`}
                                                        onClick={() =>
                                                            setDeleting([p])
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {pqrs && (
                            <Pagination
                                page={pqrs}
                                noun={
                                    filtered
                                        ? `solicitudes (de ${total} en total)`
                                        : 'solicitudes'
                                }
                            />
                        )}
                    </Card>
                )}

                {deletions.length > 0 && (
                    <Card className="gap-3 rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <History className="size-4 text-muted-foreground" />
                            <h2 className="font-bold">
                                Eliminadas recientemente en {companyName}
                            </h2>
                        </div>
                        <ul className="divide-y text-sm">
                            {deletions.map((d) => (
                                <li
                                    key={d.id}
                                    className="flex flex-col gap-0.5 py-2 sm:flex-row sm:items-center sm:gap-3"
                                >
                                    <span className="font-mono text-xs font-semibold">
                                        {d.radicado}
                                    </span>
                                    <span className="flex-1 text-muted-foreground">
                                        {d.reason}
                                        {d.detail && `: ${d.detail}`}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {d.deleted_by} ·{' '}
                                        {formatDateTime(d.deleted_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>

            <DeleteDialog
                items={deleting}
                company={company}
                companyName={companyName}
                reasons={reasons}
                onClose={() => setDeleting(null)}
                onDeleted={() => {
                    setDeleting(null);
                    setSelection({ key: pageKey, ids: [] });
                }}
            />
        </>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
    all,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    all: string;
}) {
    return (
        <div className="grid gap-1">
            <span className="text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <NativeSelect
                aria-label={label}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-44"
            >
                <option value="">{all}</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );
}

function DeleteDialog({
    items,
    company,
    companyName,
    reasons,
    onClose,
    onDeleted,
}: {
    items: PqrsListItem[] | null;
    company: string | null;
    companyName?: string;
    reasons: Option[];
    onClose: () => void;
    onDeleted: () => void;
}) {
    const [reason, setReason] = useState('');
    const count = items?.length ?? 0;

    return (
        <Dialog
            open={items !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setReason('');
                    onClose();
                }
            }}
        >
            <DialogContent>
                {items && (
                    <>
                        <DialogTitle>
                            {count === 1
                                ? `¿Eliminar la PQRS ${items[0].radicado}?`
                                : `¿Eliminar ${count} PQRS?`}
                        </DialogTitle>
                        <DialogDescription>
                            Se borran para siempre de {companyName}: la
                            solicitud, su historial y sus archivos adjuntos. No
                            se puede deshacer. Queda registrado el radicado, el
                            motivo y quién la eliminó.
                        </DialogDescription>

                        {count > 1 && (
                            <p className="rounded-lg bg-muted px-3 py-2 font-mono text-xs">
                                {items
                                    .slice(0, 6)
                                    .map((p) => p.radicado)
                                    .join(', ')}
                                {count > 6 && ` y ${count - 6} más`}
                            </p>
                        )}

                        <Form
                            key={items.map((p) => p.id).join(',')}
                            {...PqrsController.destroy.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => {
                                setReason('');
                                onDeleted();
                            }}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="empresa"
                                        value={company ?? ''}
                                    />
                                    {items.map((p) => (
                                        <input
                                            key={p.id}
                                            type="hidden"
                                            name="ids[]"
                                            value={p.id}
                                        />
                                    ))}
                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">Motivo</Label>
                                        <NativeSelect
                                            id="reason"
                                            name="reason"
                                            required
                                            value={reason}
                                            onChange={(e) =>
                                                setReason(e.target.value)
                                            }
                                        >
                                            <option value="" disabled>
                                                Elige un motivo
                                            </option>
                                            {reasons.map((r) => (
                                                <option
                                                    key={r.value}
                                                    value={r.value}
                                                >
                                                    {r.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <InputError message={errors.reason} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="detail">
                                            Detalle
                                            {reason !== 'otro' && ' (opcional)'}
                                        </Label>
                                        <Textarea
                                            id="detail"
                                            name="detail"
                                            maxLength={300}
                                            required={reason === 'otro'}
                                            rows={2}
                                            placeholder="Por ejemplo: radicada dos veces por el mismo paciente."
                                        />
                                        <InputError message={errors.detail} />
                                        <InputError
                                            message={
                                                errors.ids ?? errors.empresa
                                            }
                                        />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => {
                                                setReason('');
                                                onClose();
                                            }}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing || !reason}
                                        >
                                            {processing && <Spinner />}
                                            Eliminar definitivamente
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

CentralPqrsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'PQRS', href: pqrsRoutes.index() },
    ],
};
