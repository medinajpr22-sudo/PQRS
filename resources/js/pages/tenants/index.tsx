import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, Building2, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { FilterPills } from '@/components/pqrs/filter-pills';
import type { PillOption } from '@/components/pqrs/filter-pills';
import { PageHeader } from '@/components/pqrs/page-header';
import { Pagination } from '@/components/pqrs/pagination';
import { TenantStatusBadge } from '@/components/pqrs/status-badge';
import { TenantAvatar } from '@/components/tenants/tenant-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import tenants from '@/routes/tenants';
import type { Paginated, TenantSummary } from '@/types';

type Props = {
    tenants: Paginated<TenantSummary>;
    filters: { search: string; status: string | null };
};

const LAYOUT_LABELS: Record<string, string> = {
    corporate: 'Corporativo',
    modern: 'Moderno',
    minimal: 'Minimalista',
    salud: 'Salud',
};

const STATUS_TABS: PillOption<string | null>[] = [
    { value: null, label: 'Todas' },
    { value: 'active', label: 'Activas' },
    { value: 'suspended', label: 'Suspendidas' },
];

export default function TenantsIndex({ tenants: page, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const apply = (next: { search?: string; status?: string | null }) => {
        const merged = { ...filters, search, ...next };
        const query: Record<string, string> = {};

        if (merged.search) {
            query.search = merged.search;
        }

        if (merged.status) {
            query.status = merged.status;
        }

        router.get(
            tenants.index.url({ query }),
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Empresas" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Empresas"
                    description="Cada empresa tiene su propia base de datos, dominio, diseño y formulario PQRS."
                    actions={
                        <Button asChild>
                            <Link href={tenants.create()}>
                                <Plus />
                                Nueva empresa
                            </Link>
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <div className="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                        <FilterPills
                            label="Estado de la empresa"
                            options={STATUS_TABS}
                            value={filters.status ?? null}
                            onChange={(status) => apply({ status })}
                        />
                        <form
                            className="relative md:w-80"
                            onSubmit={(e) => {
                                e.preventDefault();
                                apply({ search });
                            }}
                        >
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por nombre, ID o NIT…"
                                className="pl-9"
                            />
                        </form>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Empresa
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Dominio
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Plan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Diseño
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Creada
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {page.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-16 text-center"
                                        >
                                            <Building2 className="mx-auto size-8 text-muted-foreground" />
                                            <p className="mt-2 font-medium">
                                                No se encontraron empresas
                                            </p>
                                        </td>
                                    </tr>
                                )}
                                {page.data.map((t) => (
                                    <tr
                                        key={t.id}
                                        className="transition-colors hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={tenants.edit(t.id)}
                                                className="flex items-center gap-3"
                                            >
                                                <TenantAvatar
                                                    name={t.name}
                                                    color={t.primary_color}
                                                    logoUrl={t.logo_url}
                                                />
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
                                                        {t.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t.nit
                                                            ? `NIT ${t.nit}`
                                                            : t.id}
                                                    </p>
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.portal_url ? (
                                                <a
                                                    href={t.portal_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 font-mono text-xs hover:underline"
                                                >
                                                    {t.domains[0]}
                                                    <ArrowUpRight className="size-3" />
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                            {t.domains.length > 1 && (
                                                <p className="text-xs text-muted-foreground">
                                                    +{t.domains.length - 1}{' '}
                                                    dominio(s)
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {t.plan}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex items-center gap-2">
                                                <span
                                                    className="size-3 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            t.primary_color,
                                                    }}
                                                />
                                                {LAYOUT_LABELS[t.layout] ??
                                                    t.layout}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <TenantStatusBadge
                                                status={t.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                            {formatDate(t.created_at)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={tenants.edit(t.id)}>
                                                    <Pencil />
                                                    Configurar
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={page} noun="empresas" />
                </Card>
            </div>
        </>
    );
}

TenantsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Empresas', href: tenants.index() },
    ],
};
