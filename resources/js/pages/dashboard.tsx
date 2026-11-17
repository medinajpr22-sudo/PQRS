import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Building2,
    CalendarDays,
    Inbox,
    Plus,
} from 'lucide-react';
import { BarList, ColumnChart } from '@/components/pqrs/charts';
import { PageHeader } from '@/components/pqrs/page-header';
import { StatCard } from '@/components/pqrs/stat-card';
import { TenantStatusBadge } from '@/components/pqrs/status-badge';
import { TenantAvatar } from '@/components/tenants/tenant-avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMonth, formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import tenants from '@/routes/tenants';
import type { PqrsStats, TenantStatus } from '@/types';

type TenantRow = {
    id: string;
    name: string;
    status: TenantStatus;
    plan: string;
    layout: string;
    primary_color: string;
    logo_url: string | null;
    portal_url: string | null;
    stats: PqrsStats;
};

type Props = {
    totals: {
        tenants: number;
        active_tenants: number;
        pqrs: number;
        open: number;
        overdue: number;
        this_month: number;
    };
    months: string[];
    monthly: number[];
    byType: Record<string, number>;
    tenants: TenantRow[];
};

const TYPE_LABELS: Record<string, string> = {
    peticion: 'Peticiones',
    queja: 'Quejas',
    reclamo: 'Reclamos',
    sugerencia: 'Sugerencias',
    felicitacion: 'Felicitaciones',
    denuncia: 'Denuncias',
};

export default function Dashboard({
    totals,
    months,
    monthly,
    byType,
    tenants: rows,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Resumen de la plataforma"
                    description="Actividad PQRS consolidada de todas las empresas."
                    actions={
                        <Button asChild>
                            <Link href={tenants.create()}>
                                <Plus />
                                Nueva empresa
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Empresas activas"
                        value={`${totals.active_tenants} / ${totals.tenants}`}
                        hint="Portales publicados"
                        icon={Building2}
                    />
                    <StatCard
                        label="PQRS totales"
                        value={formatNumber(totals.pqrs)}
                        hint={`${formatNumber(totals.this_month)} radicadas este mes`}
                        icon={Inbox}
                    />
                    <StatCard
                        label="Abiertas"
                        value={formatNumber(totals.open)}
                        hint="Radicadas o en trámite"
                        icon={CalendarDays}
                        tone="warning"
                    />
                    <StatCard
                        label="Vencidas"
                        value={formatNumber(totals.overdue)}
                        hint="Superaron el plazo de respuesta"
                        icon={AlertTriangle}
                        tone={totals.overdue > 0 ? 'danger' : 'success'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle>PQRS radicadas por mes</CardTitle>
                            <CardDescription>
                                Últimos 6 meses, todas las empresas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ColumnChart
                                caption="PQRS radicadas por mes"
                                data={months.map((m, i) => ({
                                    label: formatMonth(m),
                                    value: monthly[i] ?? 0,
                                }))}
                            />
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Por tipo de solicitud</CardTitle>
                            <CardDescription>Histórico total</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BarList
                                data={Object.entries(TYPE_LABELS)
                                    .map(([key, label]) => ({
                                        label,
                                        value: byType[key] ?? 0,
                                    }))
                                    .filter((d) => d.value > 0)
                                    .sort((a, b) => b.value - a.value)}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b py-5">
                        <CardTitle>Empresas</CardTitle>
                        <CardDescription>
                            Estado de las solicitudes en cada portal
                        </CardDescription>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-6 py-3 font-medium">
                                        Empresa
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Este mes
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Abiertas
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Vencidas
                                    </th>
                                    <th className="px-6 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-6 py-10 text-center text-muted-foreground"
                                        >
                                            Aún no hay empresas.{' '}
                                            <Link
                                                href={tenants.create()}
                                                className="font-medium text-foreground underline"
                                            >
                                                Crea la primera
                                            </Link>
                                        </td>
                                    </tr>
                                )}
                                {rows.map((t) => (
                                    <tr
                                        key={t.id}
                                        className="transition-colors hover:bg-muted/40"
                                    >
                                        <td className="px-6 py-3">
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
                                                    <p className="text-xs text-muted-foreground capitalize">
                                                        Plan {t.plan}
                                                    </p>
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            <TenantStatusBadge
                                                status={t.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatNumber(t.stats.total)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatNumber(t.stats.this_month)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatNumber(t.stats.open)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {t.stats.overdue > 0 ? (
                                                <span className="font-medium text-red-600 dark:text-red-400">
                                                    {t.stats.overdue}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    0
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {t.portal_url && (
                                                <a
                                                    href={t.portal_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                                                >
                                                    Portal
                                                    <ArrowUpRight className="size-3.5" />
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
