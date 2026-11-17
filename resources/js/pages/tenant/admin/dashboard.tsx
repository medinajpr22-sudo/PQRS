import { Head, Link } from '@inertiajs/react';
import {
    AlarmClock,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Inbox,
    Timer,
} from 'lucide-react';
import { BarList } from '@/components/pqrs/charts';
import { DueDate } from '@/components/pqrs/due-date';
import { PageHeader } from '@/components/pqrs/page-header';
import { StatCard } from '@/components/pqrs/stat-card';
import { StatusBadge } from '@/components/pqrs/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatNumber } from '@/lib/format';
import admin from '@/routes/tenant/admin';
import type { PqrsListItem, PqrsStatus } from '@/types';

type Props = {
    stats: {
        total: number;
        open: number;
        overdue: number;
        due_soon: number;
        this_month: number;
        on_time_rate: number | null;
        avg_response_days: number | null;
    };
    byType: { key: string; label: string; total: number }[];
    byStatus: { key: PqrsStatus; label: string; total: number }[];
    urgent: PqrsListItem[];
};

export default function TenantDashboard({
    stats,
    byType,
    byStatus,
    urgent,
}: Props) {
    return (
        <>
            <Head title="Resumen" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Resumen"
                    description={`${formatNumber(stats.this_month)} solicitudes radicadas este mes · ${formatNumber(stats.total)} en total`}
                    actions={
                        <Button asChild>
                            <Link href={admin.pqrs.index()}>
                                <Inbox />
                                Ver solicitudes
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Abiertas"
                        value={formatNumber(stats.open)}
                        hint="Radicadas o en trámite"
                        icon={Inbox}
                    />
                    <StatCard
                        label="Vencen en 3 días"
                        value={formatNumber(stats.due_soon)}
                        hint="Requieren atención prioritaria"
                        icon={AlarmClock}
                        tone="warning"
                    />
                    <StatCard
                        label="Vencidas"
                        value={formatNumber(stats.overdue)}
                        hint="Fuera del plazo legal"
                        icon={AlertTriangle}
                        tone={stats.overdue > 0 ? 'danger' : 'success'}
                    />
                    <StatCard
                        label="Respondidas a tiempo"
                        value={
                            stats.on_time_rate === null
                                ? '—'
                                : `${stats.on_time_rate}%`
                        }
                        hint={
                            stats.avg_response_days === null
                                ? 'Aún sin respuestas'
                                : `Promedio de respuesta: ${stats.avg_response_days} días`
                        }
                        icon={
                            stats.on_time_rate === null ? Timer : CheckCircle2
                        }
                        tone="success"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="gap-0 py-0 lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between border-b py-5">
                            <div className="space-y-1.5">
                                <CardTitle>Requieren atención</CardTitle>
                                <CardDescription>
                                    Solicitudes abiertas ordenadas por fecha de
                                    vencimiento
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link
                                    href={admin.pqrs.index({
                                        query: { overdue: 1 },
                                    })}
                                >
                                    Vencidas
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </CardHeader>
                        {urgent.length === 0 ? (
                            <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                                ¡Todo al día! No hay solicitudes abiertas.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {urgent.map((p) => (
                                    <li key={p.id}>
                                        <Link
                                            href={admin.pqrs.show(p.radicado)}
                                            className="flex items-center gap-4 px-6 py-3 transition-colors hover:bg-muted/40"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {p.radicado} ·{' '}
                                                    {p.type_label}
                                                </p>
                                                <p className="truncate text-sm font-medium">
                                                    {p.subject}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {p.requester_name}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                status={p.status}
                                                label={p.status_label}
                                                className="hidden sm:inline-flex"
                                            />
                                            <DueDate
                                                dueAt={p.due_at}
                                                open
                                                className="w-32 text-right text-sm"
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Por tipo</CardTitle>
                                <CardDescription>
                                    Histórico total
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BarList
                                    data={byType
                                        .map((t) => ({
                                            label: t.label,
                                            value: t.total,
                                        }))
                                        .sort((a, b) => b.value - a.value)}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Por estado</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2">
                                    {byStatus.map((s) => (
                                        <li key={s.key}>
                                            <Link
                                                href={admin.pqrs.index({
                                                    query: { status: s.key },
                                                })}
                                                className="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-muted"
                                            >
                                                <StatusBadge
                                                    status={s.key}
                                                    label={s.label}
                                                />
                                                <span className="text-sm font-medium tabular-nums">
                                                    {formatNumber(s.total)}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

TenantDashboard.layout = {
    breadcrumbs: [{ title: 'Resumen', href: admin.dashboard() }],
};
