import { Head, Link } from '@inertiajs/react';
import { Download, FileText, MessageSquareReply, Search } from 'lucide-react';
import { PortalCard } from '@/components/portal/portal-card';
import { StatusBadge } from '@/components/pqrs/status-badge';
import { StatusSteps } from '@/components/pqrs/status-steps';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, formatFileSize } from '@/lib/format';
import { attachment as attachmentRoute } from '@/routes/tenant/tracking';
import type { PqrsActivityItem, PqrsStatus } from '@/types';

type Props = {
    pqrs: {
        radicado: string;
        type: string;
        sede: string | null;
        risk: string | null;
        status: PqrsStatus;
        status_label: string;
        subject: string;
        requester_name: string;
        created_at: string;
        due_at: string | null;
        responded_at: string | null;
        activities: PqrsActivityItem[];
    };
};

const ACTIVITY_LABEL: Record<PqrsActivityItem['type'], string> = {
    created: 'Solicitud radicada',
    status_changed: 'Cambio de estado',
    note: 'Nota',
    response: 'Respuesta de la empresa',
    // Las asignaciones y traslados de sede son internos y nunca llegan al
    // portal; se dejan por completitud del tipo.
    assigned: 'Asignación',
    sede_changed: 'Traslado de sede',
};

export default function TrackResult({ pqrs }: Props) {
    return (
        <>
            <Head title={`Radicado ${pqrs.radicado}`} />

            <div className="space-y-5">
                <PortalCard>
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div>
                            <p className="text-xs font-medium tracking-wider text-zinc-500 uppercase">
                                Radicado
                            </p>
                            <p className="font-mono text-2xl font-bold text-(--brand)">
                                {pqrs.radicado}
                            </p>
                            <p className="mt-1 text-sm text-zinc-600">
                                {pqrs.type}
                                {pqrs.sede ? ` · Sede ${pqrs.sede}` : ''}
                                {pqrs.risk ? ` · ${pqrs.risk}` : ''} ·{' '}
                                {pqrs.subject}
                            </p>
                        </div>
                        <StatusBadge
                            status={pqrs.status}
                            label={pqrs.status_label}
                            className="self-start text-sm"
                        />
                    </div>

                    <div className="mt-8">
                        <StatusSteps status={pqrs.status} />
                    </div>

                    <dl className="mt-8 grid gap-3 text-sm sm:grid-cols-3">
                        <div className="rounded-lg bg-zinc-50 p-3">
                            <dt className="text-xs text-zinc-500">Radicada</dt>
                            <dd className="font-medium">
                                {formatDate(pqrs.created_at)}
                            </dd>
                        </div>
                        <div className="rounded-lg bg-zinc-50 p-3">
                            <dt className="text-xs text-zinc-500">
                                Fecha máxima de respuesta
                            </dt>
                            <dd className="font-medium">
                                {formatDate(pqrs.due_at)}
                            </dd>
                        </div>
                        <div className="rounded-lg bg-zinc-50 p-3">
                            <dt className="text-xs text-zinc-500">
                                Respondida
                            </dt>
                            <dd className="font-medium">
                                {pqrs.responded_at
                                    ? formatDate(pqrs.responded_at)
                                    : 'Pendiente'}
                            </dd>
                        </div>
                    </dl>
                </PortalCard>

                <PortalCard title="Historial">
                    <ol className="space-y-5">
                        {pqrs.activities.map((a) => (
                            <li key={a.id} className="flex gap-3">
                                <span
                                    className={
                                        a.type === 'response'
                                            ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-(--brand) text-(--brand-fg)'
                                            : 'flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-500'
                                    }
                                >
                                    {a.type === 'response' ? (
                                        <MessageSquareReply className="size-4" />
                                    ) : (
                                        <FileText className="size-4" />
                                    )}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">
                                        {ACTIVITY_LABEL[a.type]}
                                        <span className="ml-2 text-xs font-normal text-zinc-500">
                                            {formatDateTime(a.created_at)}
                                        </span>
                                    </p>
                                    {a.body && (
                                        <p
                                            className={
                                                a.type === 'response'
                                                    ? 'mt-2 rounded-xl border border-zinc-200 bg-(--brand-soft) p-4 text-sm whitespace-pre-line text-zinc-800'
                                                    : 'mt-0.5 text-sm whitespace-pre-line text-zinc-600'
                                            }
                                        >
                                            {a.body}
                                        </p>
                                    )}
                                    {a.attachments.length > 0 && (
                                        <ul className="mt-2 flex flex-wrap gap-2">
                                            {a.attachments.map((f) => (
                                                <li key={f.id}>
                                                    <a
                                                        href={
                                                            attachmentRoute({
                                                                pqrs: pqrs.radicado,
                                                                attachment:
                                                                    f.id,
                                                            }).url
                                                        }
                                                        className="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs hover:bg-zinc-50"
                                                    >
                                                        <Download className="size-3.5" />
                                                        {f.name}
                                                        <span className="text-zinc-400">
                                                            {formatFileSize(
                                                                f.size,
                                                            )}
                                                        </span>
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ol>
                </PortalCard>

                <div className="flex justify-center">
                    <Button variant="outline" asChild>
                        <Link href="/consultar">
                            <Search />
                            Consultar otra solicitud
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

TrackResult.layout = {
    active: 'track',
    title: 'Estado de tu solicitud',
};
