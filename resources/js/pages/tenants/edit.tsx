import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarDays,
    ClipboardList,
    Inbox,
    Palette,
    Settings2,
} from 'lucide-react';
import { useState } from 'react';
import { StatCard } from '@/components/pqrs/stat-card';
import { TenantStatusBadge } from '@/components/pqrs/status-badge';
import { DangerZone } from '@/components/tenants/danger-zone';
import { FormBuilder } from '@/components/tenants/form-builder';
import { GeneralForm } from '@/components/tenants/general-form';
import { TenantAvatar } from '@/components/tenants/tenant-avatar';
import { ThemeEditor } from '@/components/tenants/theme-editor';
import type { FontOption } from '@/components/tenants/theme-editor';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import tenants from '@/routes/tenants';
import type {
    FormSchema,
    PqrsStats,
    TenantSummary,
    TenantTheme,
} from '@/types';

type Props = {
    tenant: TenantSummary & {
        theme: TenantTheme;
        form_schema: FormSchema;
        stats: PqrsStats;
    };
    plans: string[];
    fonts: FontOption[];
};

type Tab = 'general' | 'design' | 'form';

const TABS: { key: Tab; label: string; icon: typeof Palette }[] = [
    { key: 'general', label: 'General', icon: Settings2 },
    { key: 'design', label: 'Diseño del portal', icon: Palette },
    { key: 'form', label: 'Formulario PQRS', icon: ClipboardList },
];

function initialTab(): Tab {
    if (typeof window === 'undefined') {
        return 'general';
    }

    const hash = window.location.hash.replace('#', '');

    return (['general', 'design', 'form'] as Tab[]).includes(hash as Tab)
        ? (hash as Tab)
        : 'general';
}

export default function EditTenant({ tenant, plans, fonts }: Props) {
    const [tab, setTab] = useState<Tab>(initialTab);

    const selectTab = (next: Tab) => {
        setTab(next);
        window.history.replaceState(window.history.state, '', `#${next}`);
    };

    return (
        <>
            <Head title={tenant.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <TenantAvatar
                            name={tenant.name}
                            color={tenant.theme.primary_color}
                            logoUrl={tenant.theme.logo_url}
                            className="size-14 text-base"
                        />
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {tenant.name}
                                </h1>
                                <TenantStatusBadge status={tenant.status} />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                <span className="font-mono">
                                    {tenant.domains[0]}
                                </span>
                                {tenant.nit && <> · NIT {tenant.nit}</>}
                                <span className="capitalize">
                                    {' '}
                                    · Plan {tenant.plan}
                                </span>
                            </p>
                        </div>
                    </div>
                    {tenant.portal_url && (
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <a
                                    href={`${tenant.portal_url}/admin`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Panel de la empresa
                                    <ArrowUpRight />
                                </a>
                            </Button>
                            <Button asChild>
                                <a
                                    href={tenant.portal_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Ver portal
                                    <ArrowUpRight />
                                </a>
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="PQRS totales"
                        value={formatNumber(tenant.stats.total)}
                        icon={Inbox}
                    />
                    <StatCard
                        label="Este mes"
                        value={formatNumber(tenant.stats.this_month)}
                        icon={CalendarDays}
                    />
                    <StatCard
                        label="Abiertas"
                        value={formatNumber(tenant.stats.open)}
                        icon={ClipboardList}
                        tone="warning"
                    />
                    <StatCard
                        label="Vencidas"
                        value={formatNumber(tenant.stats.overdue)}
                        icon={AlertTriangle}
                        tone={tenant.stats.overdue > 0 ? 'danger' : 'success'}
                    />
                </div>

                <div className="border-b">
                    <nav className="-mb-px flex gap-1 overflow-x-auto">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                onClick={() => selectTab(t.key)}
                                className={cn(
                                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                                    tab === t.key
                                        ? 'border-foreground text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <t.icon className="size-4" />
                                {t.label}
                            </button>
                        ))}
                    </nav>
                </div>

                {tab === 'general' && (
                    <div className="max-w-4xl space-y-6">
                        <GeneralForm tenant={tenant} plans={plans} />
                        <DangerZone tenant={tenant} />
                    </div>
                )}

                {tab === 'design' && (
                    <ThemeEditor
                        tenantId={tenant.id}
                        tenantName={tenant.name}
                        theme={tenant.theme}
                        fonts={fonts}
                    />
                )}

                {tab === 'form' && (
                    <div className="max-w-5xl">
                        <FormBuilder
                            tenantId={tenant.id}
                            schema={tenant.form_schema}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

EditTenant.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Empresas', href: tenants.index() },
    ],
};
